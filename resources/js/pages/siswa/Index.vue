<template>
  <div>
    <loading
      color="#007bff"
      loader="dots"
      :height="45"
      :width="45"
      :active.sync="isLoading"
      :is-full-page="true"
    ></loading>
    <loading
      color="#007bff"
      :opacity="0.8"
      loader="dots"
      :height="45"
      :width="45"
      :active.sync="connection"
      :is-full-page="true"
    ><div class="text-xl text-center">Kamu terputus dengan server<br /> silakan cek koneksi internet kamu</div></loading>
    <loading
      color="#007bff"
      :opacity="0.8"
      loader="dots"
      :height="45"
      :width="45"
      :active.sync="isBlockedPeserta"
      :is-full-page="true"
    ><div class="text-xl text-center">Akun anda dibekukan karena: <u>{{ peserta.block_reason }}</u> <br /> Hubungi administrator untuk membuka blok</div></loading>
    
    <div v-if="show_disable_screen">Layar harus full-screen untuk mengerjakan testmate-cbt ini</div>
    <template v-if="!show_disable_screen">
      <div class="pt-6 pb-24 shadow-sm headerbg text-white">
        <div class="flex justify-between flex-col sm:flex-row">
          <div class="flex items-center rounded-md items-center justify-center flex"
          v-if="typeof setting.sekolah != 'undefined'" >
          <img src="/img/testmate_icon_logotext_light_logo.png" class="h-12" style="padding: 0 0 0 10;"/>
            <!-- <div class="flex flex-col">
            </div> -->
          </div>

          <div class="flex space-x-2 justify-end"  style="padding: 0 10 0 0;">
            <div class="flex flex-col">
              <p class="font-semibold text-right">{{ peserta.nama }}</p>
              <p class="text-sm text-right">{{ peserta.no_ujian }}</p>
            </div>
            <button class="h-12 w-12 flex items-center justify-center bg-white text-gray-600 rounded-md hover:shadow-lg"
            :class="{ isLoading : 'bg-gray-100' }"
            :disabled="isLoading"
            @click="logout"
            >
              <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="h-7 feather feather-log-out"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path><polyline points="16 17 21 12 16 7"></polyline><line x1="21" y1="12" x2="9" y2="12"></line></svg>
            </button>
          </div>
        </div>
      </div>
      <router-view></router-view>
    </template>
    <div class="fixed bottom-0 left-0 w-full border-t border-gray-300 text-gray-600 py-2 px-4 text-center bg-white">
      <span class="text-sm">&copy; {{ year }} TestMate </span>
    </div>
  </div>
</template>
<script>
import { mapState, mapActions, mapGetters } from 'vuex'
import { successToas, errorToas} from '../../entities/notif'
import { showSweetError } from '../../entities/alert'
import Loading from 'vue-loading-overlay'
import io from 'socket.io-client'
import { async } from 'q'

export default {
  name: 'IndexUjian',
  components: {
    Loading,
  },
  data() {
    return {
      channel: '',
      connection: false,
      is_getted: false,
      enable_socket: process.env.MIX_ENABLE_SOCKET,
      version: process.env.MIX_APP_VERSION,
      year: '',
      isBlockedPeserta: false,
      only_fullscreen: false,
      show_disable_screen: false,
      out_screen_timer: 0,
      out_screen_interval: 0
    }
  },
  computed: {
    ...mapGetters(['isLoading', 'setting']),
    ...mapState('siswa_user', {
      peserta: state => state.pesertaDetail
    }),
    ...mapState('siswa_ujian', {
      uncomplete: state => state.uncomplete,
      detail: state => state.filledUjian.detail
    }),
    ...mapState('siswa_channel', ['socket'])
  },
  methods: {
    ...mapActions('siswa_jadwal',['ujianAktif']),
    ...mapActions('siswa_auth',['logoutPeserta']),
    ...mapActions('siswa_ujian',['getPesertaDataUjian', 'getPesertaUjian', 'getUncompleteUjian', 'leaveCounterUjian', 'blockMePlease']),
    showError(err) {
      showSweetError(this, err)
    },
    async logout() {
      try {
        await this.logoutPeserta()
        localStorage.removeItem('token')
        this.$store.state.token = localStorage.getItem('token')
        this.$store.state.siswa_ujian.filledUjian = []
        this.$router.push('/')
      } catch (error) {
        this.showError(error)
      }
    },
    async listenEventChange() {
      if (document.visibilityState == "visible") {
        if (this.enable_socket === "oke") {
          this.socket.emit('in_tab_student', {
            user: this.peserta.id,
            channel: this.channel
          });
        }
      } else {
        if (this.enable_socket === "oke") {
          this.socket.emit('not_in_tab_student', {
            user: this.peserta.id,
            channel: this.channel
          });
        }
        if(typeof this.detail.id != 'undefined') {
          let net = await this.leaveCounterUjian({
            id: this.detail.id
          })
          if(net.data.status == '0') {
            this.$store.commit('siswa_user/_block_peserta', net.data.block_reason)
            this.isBlockedPeserta = true
          }
        }
      }
    },
    async listenFullScreenChange() {
      if(this.only_fullscreen) {
        this.out_screen_timer = 0
        clearTimeout(this.out_screen_interval)
        
        if (window.innerHeight == screen.height) {
            console.log('FULL SCREEN');
            this.show_disable_screen = false
        } else {
            this.out_screen_interval=setTimeout(async () => {
              await this.blockMePlease({
                id: this.detail.id,
                reason: 'Tidak berada pada mode full-screen'
              })
            }, 3000)
            console.log('NORMAL SCREEN');
            this.show_disable_screen = true
        }
      }
    }
  },
  async created() {
    let d = new Date()
    this.year = d.getFullYear()

    try {
      if(this.$route.name != 'ujian.while') {
        await this.ujianAktif()
        await this.getPesertaUjian()
        await this.getUncompleteUjian()
      }
      this.channel = 'student_connect_channel'

      if (this.enable_socket === "oke") {
        if (!this.socket.connected) {
          this.$store.state.siswa_channel.socket = io(process.env.MIX_SOCKET_URL,{
            autoConnect: false,
            pingInterval: 1000,
            pingTimeout: 5000,
          }),
          this.socket.open();
        }

          this.socket.on('connect', () => {
            console.log("CONNECTING....")
            this.connection = false
            if(this.socket.connected) {
              if (typeof this.peserta.id != 'undefined' && !this.is_getted) {
                const peserta = JSON.parse(JSON.stringify(this.peserta))
                peserta.intab = true
                this.socket.emit('getin_student', {
                  user: peserta,
                  channel: this.channel
                });
                this.is_getted = true
              }
            }
          });

          this.socket.on('connect_failed', () => {
            this.connection = true
            this.is_getted = false
            this.socket.emit('not_in_tab_student', {
              user: this.peserta.id,
              channel: this.channel
            });
          });

          this.socket.on('disconnect', () => {
            this.connection = true
            this.is_getted = false
            this.socket.emit('not_in_tab_student', {
              user: this.peserta.id,
              channel: this.channel
            });
          });
      }
    } catch (error) {
      this.showError(error)
    }
  },
  mounted() {
    document.addEventListener("visibilitychange", this.listenEventChange);
    document.addEventListener('fullscreenchange', this.listenFullScreenChange);
    window.addEventListener('resize', this.listenFullScreenChange);

    if(this.setting.only_fullscreen == true) {
        this.only_fullscreen = true

        this.listenFullScreenChange()
    }
  },
  watch: {
    uncomplete(val) {
      if(this.$route.name != 'ujian.while' && typeof val.jadwal_id != 'undefined') {
        if (val.status_ujian == 3) {
          this.$router.replace({
            name: 'ujian.while'
          })
        } else if (val.status_ujian == 0 && this.$route.name != 'ujian.prepare') {
          this.$router.replace({
            name: 'ujian.prepare'
          })
        }
      }
    },
    peserta(v) {
      if(typeof v.id != 'undefined') {
        if (this.enable_socket === "oke") {
          if(this.socket.connected) {
            if (!this.is_getted) {
              const peserta = JSON.parse(JSON.stringify(this.peserta))
              peserta.intab = true
              this.socket.emit('getin_student', {
                user: peserta,
                channel: this.channel
              });
              this.is_getted = true
            }
          }
        }
      }
    },
    setting(v) {
      if(v.only_fullscreen == true) {
        this.only_fullscreen = true

        this.listenFullScreenChange()
      }
    }
  },
  beforeDestroy() {
    document.removeEventListener("visibilitychange", this.listenEventChange)
    document.removeEventListener("fullscreenchange");
    window.removeEventListener("resize")
  },
  destroyed() {
    this.$store.commit('siswa_user/REMOTE_PESERTA_DETAIL')

    if (this.enable_socket === 'oke') {
      this.socket.emit('exit', { channel: this.channel })
      this.socket.close()
    }
  }
}
</script>
<style>
.headerbg {
  background: linear-gradient(90deg, rgba(25,32,78,1) 0%, rgba(9,9,121,1) 35%, rgba(25,32,78,1) 100%);
}
</style>