<template>
  <div class="container md:mx-auto flex flex-col justify-center space-y-4 lg:flex-row lg:space-y-0 lg:space-x-4 -mt-12 sm:-mt-24">
    <div class="w-full lg:py-4 lg:px-4 mb-20">
      <div class="bg-white border-gray-300 shadow sm:shadow-2xl rounded-t-2xl rounded-b-2xl"  v-if="filleds && typeof filleds[questionIndex] != 'undefined'">
        <div class="pt-4 pb-2 pr-2 flex justify-between border-b border-gray-300 mb-2 items-center" v-show="!focus">
          <div class="flex items-center">
			      <p class="font-bold w-12 h-10 bg-gray-200 flex items-center pl-2 text-lg rounded-r-full text-gray-700 border-t border-r border-b border-gray-300">{{ questionIndex+1 }}</p>
            <p class="font-medium text-gray-700 px-2">{{ tipeSoalText() }}</p>
          </div>
          <div class="flex justify-end space-x-2 mb-2 items-center">
			      <div class="rounded-md text-white font-bold flex" v-html="prettyTime">
			      </div>
          </div>
        </div>
		    <div class="py-2 px-2 my-2 border-b border-gray-300 flex justify-between">
          <div class="flex flex-col space-y-1">
           <p class="text-xs text-gray-600">Ukuran soal</p>
            <input type="range" id="vol" name="vol" min="0" max="3" value="1" @change="onChangeRange">
          </div>
			    <!-- <div class="flex space-x-1">
				    <button class="py-1 px-1 rounded-md bg-gray-100 text-gray-600 border border-gray-300 hover:shadow-lg"
            @click="modalQuestion = true"
            v-show="!focus"
            :disabled="!listening">
					    <app-line-icon></app-line-icon>
				    </button>
				    <button class="py-1 px-1 rounded-md bg-gray-100 text-gray-600 border border-gray-300 hover:shadow-lg"
            @click="focus = !focus"
            >
              <expand-line-icon v-show="!focus"></expand-line-icon>
              <minimize-line-icon v-show="focus"></minimize-line-icon>
				    </button>
			    </div> -->
		    </div>
		    <div class="py-8 px-2 sm:px-8"
        :class="textSize"
        v-if="typeof filleds[questionIndex] != 'undefined'">
          <div class="my-2"
          v-if="[1,2,3].includes(parseInt(filleds[questionIndex].soal.layout))"
          >
            <RenderString
            :string="changeToZoomer(filleds[questionIndex].soal.pertanyaan)" />
          </div>
			    <div class="flex flex-col space-y-3 mt-5"
          v-if="filleds[questionIndex].soal.layout == 1"
          >
            <template
            v-if="[1,3,4].includes(filleds[questionIndex].soal.tipe_soal)"
            >
              <div class="flex space-x-1"
                   v-for="(jawab,index) in filleds[questionIndex].soal.jawabans"
                   :key="index">
                <div
                  v-if="[1,3].includes(parseInt(filleds[questionIndex].soal.tipe_soal))">
                  <div class="flex items-center mr-4 mb-4">
                    <input :id="'radio1'+index"
                           v-model="selected" type="radio" name="jwb"
                           :value="jawab.id" class="hidden bgdark"
                           :disabled="isLoadinger || isLoading"
                           @change="selectOption(index)"/>
                    <label :for="'radio1'+index" class="flex items-center cursor-pointer text-xl">
                      <span class="w-6 h-6 text-sm inline-block mr-2 rounded-full border border-gray-400 flex-no-shrink flex items-center justify-center uppercase">{{ charIndex(index) }}</span>
                    </label>
                  </div>
                </div>
                <div
                  v-if="4 == parseInt(filleds[questionIndex].soal.tipe_soal)"
                >
                  <div class="bg-white border-2 rounded border-gray-400 w-6 h-6 flex flex-shrink-0 justify-center items-center mr-2 focus-within:border-blue-500">
                    <input
                      :checked="filleds[questionIndex].jawab_complex.includes(jawab.id)"
                      :value="jawab.id"
                      :disabled="isLoadinger || isLoading"
                      @change="changeCheckbox($event, index)"
                      type="checkbox" class="opacity-0 absolute">
                    <svg class="fill-current hidden w-4 h-4 text-green-500 pointer-events-none" viewBox="0 0 20 20"><path d="M0 11l2-2 5 5L18 3l2 2L7 18z"/></svg>
                  </div>
                </div>
                <!-- <div v-html="jawab.text_jawaban"></div> -->
                <RenderString
                  :string="changeToZoomer(jawab.text_jawaban)" />
              </div>
            </template>
				  </div>
				</div>
		    <div class="py-4 px-2 sm:px-4 flex justify-between border-t border-gray-300 items-center"  v-show="!focus">
			  <!-- check button if total q -->
          <button class="py-1 px-3 border-2 rounded-md hover:shadow-lg sm:flex sm:items-center sm:space-x-2"
          :class="isLoadinger ? 'bgdark text-white' : 'bgdark text-white'"
          :disabled="isLoadinger || !listening"
          v-if="questionIndexTotal+1 != this.detail.jumlah_soal"
          @click="next()"
          >
				    <span class="hidden bgdark sm:block">Selanjutnya</span> <next-line-icon></next-line-icon>
			    </button>
          
          <button class="py-1 px-3 border-2 rounded-md hover:shadow-lg sm:flex sm:items-center sm:space-x-2 bgdark"
          :class="isLoadinger ? 'bg-green-200 text-white' : 'bg-green-400 text-white'"
          :disabled="isLoadinger || !listening"
          v-if="questionIndexTotal+1 == this.detail.jumlah_soal"
          @click="modalConfirm = true"
          >
				    <span class="hidden bgdark sm:block">Selesai</span> <next-line-icon></next-line-icon>
			    </button>
          <!-- <button class="py-1 px-3 border-2 rounded-md hover:shadow-lg sm:flex sm:items-center sm:space-x-2 bgdark"
          v-if="questionIndexTotal+1 == this.detail.jumlah_soal"
                  :class="isLoadinger ? 'bgdark text-white' : 'bgdark text-white'"
                  :disabled="isLoadinger || !listening"
                  @click="kosongExistAlert"
          >
            <span class="hidden bgdark sm:block">Selesai</span> <next-line-icon></next-line-icon>
          </button> -->
		    </div>
      </div>
    </div>
    <modal-confirm v-if="modalConfirm" @close="modalConfirm = false" @finish="selesai"></modal-confirm>
    <modal-question v-if="modalQuestion" @close="modalQuestion = false" @toland="toLand"></modal-question>
    <modal-direction v-if="modalDirection" @close="playDirection" @mute="modalDirection = false"></modal-direction>
  </div>
</template>
<script>
import {
  vuex_state,
  vuex_methods,
  vue_data,
  vue_computed,
  vue_methods
} from '../../entities/ujian'
import NextLineIcon from '../../components/NextLineIcon'
import PrevLineIcon from '../../components/PrevLineIcon'
import AppLineIcon from '../../components/AppLineIcon'
import ModalConfirm from '../../components/ModalConfirm'
import ModalQuestion from '../../components/ModalQuestion'
import ModalDirection from '../../components/ModalDirection'
import ExpandLineIcon from '../../components/ExpandLineIcon'
import MinimizeLineIcon from '../../components/MinimizeLineIcon'
import RenderString from '../../components/siswa/RenderString'
import AudioPlayer from '../../components/siswa/AudioPlayer.vue'
import 'vue-loading-overlay/dist/vue-loading.css'
import _ from 'lodash'

export default {
  components: {
    NextLineIcon,
    PrevLineIcon,
    AppLineIcon,
    ExpandLineIcon,
    MinimizeLineIcon,
    AudioPlayer,
    ModalConfirm,
    ModalQuestion,
    ModalDirection
  },
  data() {
    return vue_data
  },
  computed: {
    ...vuex_state,
    ...vue_computed
  },
  methods: {
    ...vuex_methods,
    ...vue_methods,
    onInput: _.debounce(function (value) {
      this.inputJawabEssy(value)
    }, 300),
    onInputSetujuTidak: _.debounce(function (value) {
      this.sendAnswerSetujuTidak(value)
    }, 300),
    doubtExistAlert() {
      this.$swal('Hei..', 'Jawabanmu masih ada ' + this.hei.ragu +' yang ragu-ragu, kamu bisa cek pada nomor yang berwarna kuning.','warning')
    },
    kosongExistAlert() {
      this.$swal('Hei..', 'Jawabanmu masih ada ' + this.hei.kosong +' yang belum diisi, kamu bisa cek pada nomor yang berwarna abu.','warning')
    },
    next() {  

      this.filledAllSoalNext()
      this.questionIndexTotal = this.questionIndexTotal + 1
      console.log(this.questionIndexTotal)

 }
  },
  async created() {
    try {
      await this.filledAllSoal()
    } catch (error) {
      this.showError(error)
    }
  },
  watch: {
    
    questionIndex() {   

      this.selected = this.filleds[0].jawab
      this.ragu = this.filleds[0].ragu_ragu
      if(this.filleds[0].soal.audio != null) {
        this.audio = this.filleds[0].soal.audio
      }
      else {
        this.audio = ''
      }

      if(this.filleds[0].soal.direction != null) {
        this.direction = new Audio('/storage/audio/'+this.filleds[0].soal.direction)
      } else {
        if(this.direction != '') {
          this.direction.pause()
        }
        this.direction = ''
      }
    },
    filleds() {
      this.questionIndex = 0
    },
    detail(val) {
      clearInterval(this.$store.state.siswa_ujian.interval)
      if (typeof val != 'undefined') {
        this.time = val.sisa_waktu
        this.$store.state.siswa_ujian.interval = setInterval( () => {
          if (this.time > 0) {
            this.time--
          } else {
            this.selesai()
          }
        }, 1000 )
      }
    },
    async jadwal(val) {
      if(typeof this.jadwal.jadwal != 'undefined') {
        await this.filledAllSoal()
        this.start()
      }
    },
    direction(val) {
      if(val != '') {
        if(this.hasdirec.includes(this.filleds[this.questionIndex].soal.id)) {
          return
        }
        this.modalDirection = true
      }
    }
  }

}
</script>
<style>
.bgdark {
  background-color: #19144e !important;
  background: linear-gradient(90deg, rgba(25,32,78,1) 0%, rgba(25,32,78,1) 35%, rgba(25,32,78,1) 100%) !important;
}

input[type="radio"]:checked + label {
  color: #19144e !important;
}

input[type="radio"]:checked + label span {
  background-color: #19144e;
  box-shadow: 0px 0px 0px 2px white inset;
}

input[type="range"] {
  -webkit-appearance: none;
  background: rgba(255, 255, 255, 0.6);
  border-radius: 5px;
  background: #19144e;
  color: white;
  cursor: ew-resize;
  box-shadow: 0 0 2px 0 #555;
  width: 100%;
}

</style>