<?php

namespace App\Http\Controllers;

use App\JawabanPeserta;
use App\Peserta;
use App\SiswaUjian;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StudentReportController extends Controller
{
    public function individualReport(Request $request, $pesertaId)
    {
        // Ambil data peserta
        $peserta = Peserta::findOrFail($pesertaId);

        // Ambil data soal yang dijawab oleh peserta
        $jawabanPeserta = JawabanPeserta::where('peserta_id', $pesertaId)
            ->with('soal')
            ->get();

        // Hitung Skor Terkalibrasi (langsung di controller)
        $skor_terkalibrasi = $this->calculateSkorTerkalibrasi($jawabanPeserta);

        // Hitung Tingkat Kemampuan (langsung di controller)
        $tingkat_kemampuan = $this->calculateTingkatKemampuan(
            $skor_terkalibrasi
        );

        // Menghitung Rata-rata Kinerja
        $jadwal = SiswaUjian::where('peserta_id', $peserta->id)
            ->orderBy('created_at', 'desc')
            ->first('jadwal_id');

        // Hitung Total Skor (Contoh sederhana)
        $totalSkor = $jawabanPeserta->where('is_benar', true)->count();

        // Hitung Ranking (Asumsikan sudah dihitung di DB, di kolom 'ranking_kelas')
        $rankingKelas = $peserta->ranking_kelas;

        // Menghitung Kemampuan Per Topik
        $kemampuanPerTopik = $this->getKemampuanPerTopik($peserta, $jawabanPeserta);

        // Menghitung Riwayat Kinerja (contoh sederhana, perlu diimplementasikan)
        $riwayatKinerja = $this->getRiwayatKinerja($peserta);

        // Hitung Rata-rata Kelas
        $rataRataKelas = $this->calculateRataRataKelas($jadwal->jadwal_id);

        // Hitung Persentil
        $persentil = $this->calculatePersentil($skor_terkalibrasi, $jadwal->jadwal_id);

        // Generate Interpretasi Kemampuan
        $interpretasiKemampuan = $this->generateInterpretasiKemampuan($peserta->tingkat_kemampuan);

        // Generate Interpretasi Perbandingan Kinerja
        $interpretasiPerbandinganKinerja = $this->generateInterpretasiPerbandinganKinerja($persentil);

        // 1. Hitung jumlah jawaban benar
        $jawabanBenarCount = JawabanPeserta::whereIn('soal_id', $jawabanPeserta->pluck('soal_id'))
            ->where('iscorrect', true)
            ->count();

        $totalPesertaYangMendapatSoal = JawabanPeserta::whereIn('soal_id', $jawabanPeserta->pluck('soal_id'))
            ->count();

        // Informasi untuk setiap soal
        $analisisSoal = $jawabanPeserta->map(function ($jawaban) {
            $soal = $jawaban->soal;
            $dataAnalisis = [
                'nomor_soal' => $soal->id,
                'pertanyaan' => $soal->pertanyaan,
                'jawaban_siswa' => $jawaban->jawaban,
                'kunci_jawaban' => $soal->kunci,
                'status' => $jawaban->is_benar ? 'Benar' : 'Salah',
                'daya_pembeda' => $soal->a_calibrated,
                'kesulitan' => $soal->b_calibrated,
                'probabilitas_tebakan' => $soal->c_calibrated,
                'topic' => $soal->topic,
                // 'indeks_kesulitan' => $soal->indeks_kesulitan,
                // 'indeks_diskriminasi' => $soal->indeks_diskriminasi,
            ];
            return $dataAnalisis;
        });

        // Mendapatkan skor peserta untuk soal ini
        $skorPeserta = JawabanPeserta::whereIn('soal_id', $jawabanPeserta->pluck('soal_id'))
            ->select('peserta_id', DB::raw('SUM(CASE WHEN iscorrect = \'true\' THEN 1 ELSE 0 END) AS total_benar'))
            ->groupBy('peserta_id')
            ->get();

        // Mengurutkan skor peserta
        $skorPeserta = $skorPeserta->sortByDesc('total_benar');

        // Mengambil kelompok atas (27%)
        $kelompokAtas = $skorPeserta->take($skorPeserta->count() * 0.27);

        // Mengambil kelompok bawah (27%)
        $kelompokBawah = $skorPeserta->skip($skorPeserta->count() * 0.73)
            ->take($skorPeserta->count() * 0.27);

        if ($kelompokAtas->count() == 0) {
            $kelompokAtas = 1;
        }

        if ($kelompokBawah->count() == 0) {
            $kelompokBawah = 1;
        }

        // Hitung persentase jawaban benar di masing-masing kelompok
        $persentaseAtas = JawabanPeserta::whereIn('soal_id', $jawabanPeserta->pluck('soal_id'))
            ->whereIn('peserta_id', $skorPeserta->pluck('peserta_id'))
            ->where('iscorrect', true)
            ->count() / $kelompokAtas * 100;

        $persentaseBawah = JawabanPeserta::whereIn('soal_id', $jawabanPeserta->pluck('soal_id'))
            ->whereIn('peserta_id', $skorPeserta->pluck('peserta_id'))
            ->where('iscorrect', true)
            ->count() / $kelompokBawah * 100;

        // return $persentaseBawah;
        // Menghitung indeks diskriminasi
        $indeksDiskriminasi = $persentaseAtas - $persentaseBawah;

        // Generate Saran untuk Guru
        $saran = $this->generateSaran($persentil, $peserta->tingkat_kemampuan, $analisisSoal, $jawabanBenarCount, $totalPesertaYangMendapatSoal, $indeksDiskriminasi, $riwayatKinerja);

        return response()->json([
            'peserta' => $peserta,
            'tingkat_kemampuan' => $tingkat_kemampuan,
            'totalSkor' => $totalSkor,
            'rankingKelas' => $rankingKelas,
            // 'statusKelulusan' => $statusKelulusan,
            'rataRataKelas' => $rataRataKelas,
            'persentil' => $persentil,
            'kemampuanPerTopik' => $kemampuanPerTopik,
            'riwayatKinerja' => $riwayatKinerja,
            'interpretasiKemampuan' => $interpretasiKemampuan,
            'interpretasiPerbandinganKinerja' => $interpretasiPerbandinganKinerja,
            'saran' => $saran,

        ]);
    }

    // ---------------------------- (Fungsi Menghitung) ----------------------------

// Fungsi untuk menghitung skor terkalibrasi
    private function calculateSkorTerkalibrasi($jawabanPeserta)
    {
        // Implementasikan fungsi irtProbability dari model IRT yang kamu gunakan
        $thetaEstimate = 0; // Inisialisasi nilai theta (tingkat kemampuan)
        $previousTheta = null;
        $tolerance = 0.001; // Toleransi untuk konvergensi
        $maxIterations = 1; // Batas iterasi
        $iteration = 0;

        // Iterasi untuk mencari theta optimal
        while ($iteration < $maxIterations) {
            // 1. Menghitung Probabilitas Jawaban Benar
            $probabilities = $jawabanPeserta->map(function ($jawaban) use ($thetaEstimate) {
                return $this->irtProbability(
                    $jawaban->soal->a_calibrated,
                    $jawaban->soal->b_calibrated,
                    $jawaban->soal->c_calibrated,
                    $thetaEstimate
                );
            });

            // 2. Menghitung Log-Likelihood
            $logLikelihood = $this->calculateLogLikelihood($jawabanPeserta, $probabilities);

            // 3. Menghitung Gradient Log-Likelihood
            $gradient = $this->calculateGradient($jawabanPeserta, $probabilities);

            // 4. Perbarui Theta
            $thetaEstimate = $thetaEstimate - 0.1 * $gradient;

            // Periksa Konvergensi
            if ($previousTheta !== null && abs($thetaEstimate - $previousTheta) < $tolerance) {
                break;
            }
            $previousTheta = $thetaEstimate;
            $iteration++;
        }

        // Setelah Konvergensi:
        $skorTerkalibrasi = $thetaEstimate;
        return $skorTerkalibrasi;
    }

// Fungsi untuk menghitung Probabilitas Berdasarkan Model 3PL
    private function irtProbability($a, $b, $c, $theta)
    {
        return $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)));
    }

// Fungsi untuk menghitung Log-Likelihood
    private function calculateLogLikelihood($jawabanPeserta, $probabilities)
    {
        $logLikelihood = 0;
        foreach ($jawabanPeserta as $index => $jawaban) {
            if ($jawaban->is_benar) {
                $logLikelihood += log($probabilities[$index]);
            } else {
                $logLikelihood += log(1 - $probabilities[$index]);
            }
        }
        return $logLikelihood;
    }

// Fungsi untuk menghitung Gradient Log-Likelihood
    private function calculateGradient($jawabanPeserta, $probabilities)
    {
        $gradient = 0;
        foreach ($jawabanPeserta as $index => $jawaban) {
            $a = $jawaban->soal->a_calibrated;
            $b = $jawaban->soal->b_calibrated;
            $c = $jawaban->soal->c_calibrated;
            $probability = $probabilities[$index];
            $below = (1 - $c) * $probability * (1 - $probability);
            if ($below == 0) {
                $below = 1;
            }

            $gradient += $a * ($jawaban->iscorrect - $probability) / (
                $below
            );

        }
        return $gradient;
    }

    private function calculateTingkatKemampuan($skorTerkalibrasi)
    {
        // Dalam model IRT 3PL,  nilai theta adalah skor terkalibrasi
        return $skorTerkalibrasi;
    }

    private function calculateRataRataKelas($jadwal_id)
    {
        $skorRata = JawabanPeserta::where('jadwal_id', $jadwal_id)
            ->avg('theta_akhir');

        return round($skorRata, 2);
    }

    private function calculatePersentil($skorTerkalibrasi, $jadwal_id)
    {
        $pesertaTes = JawabanPeserta::where('jadwal_id', $jadwal_id)
            ->get();

        $totalPeserta = $pesertaTes->count();

        $jumlahLebihRendah = $pesertaTes->where('skor_terkalibrasi', '<', $skorTerkalibrasi)->count();
        $persentil = ($jumlahLebihRendah / $totalPeserta) * 100;
        return round($persentil, 2);
    }

    // ---------------------------- (Fungsi untuk Data Tambahan) ----------------------------

    private function getKemampuanPerTopik(Peserta $peserta, $jawabanPeserta)
    {
        $kemampuanPerTopik = [];

        // Grouping Jawaban berdasarkan Topik
        $jawabanByTopic = $jawabanPeserta->groupBy('soal.topic');

        // Hitung Kemampuan Per Topik (misalnya, skor rata-rata)
        foreach ($jawabanByTopic as $topic => $jawaban) {
            $totalBenar = $jawaban->where('is_benar', true)->count();
            $totalSoal = $jawaban->count();
            $persentaseBenar = ($totalBenar / $totalSoal) * 100;
            $kemampuanPerTopik[$topic] = $persentaseBenar;
        }
        return $kemampuanPerTopik;
    }

    private function getRiwayatKinerja(Peserta $peserta)
    {
        // Asumsikan ada model riwayat (RiwayatPeserta)
        $riwayat = SiswaUjian::where('peserta_id', $peserta->id)
            ->orderBy('created_at', 'desc') // Urutkan berdasarkan tanggal
            ->with('hasil')
            ->with('jadwal')
            ->get();

        return $riwayat;
    }

    // ---------------------------- (Fungsi Interpretasi) ----------------------------

    private function generateInterpretasiKemampuan($tingkatKemampuan)
    {
        if ($tingkatKemampuan > 1) {
            return "Siswa ini memiliki kemampuan yang sangat baik dalam mata pelajaran ini.";
        } else if ($tingkatKemampuan > 0.5) {
            return "Siswa ini memiliki kemampuan yang baik dalam mata pelajaran ini.";
        } else if ($tingkatKemampuan > 0) {
            return "Siswa ini memiliki kemampuan yang sedang dalam mata pelajaran ini.";
        } else {
            return "Siswa ini memiliki kemampuan yang perlu ditingkatkan dalam mata pelajaran ini.";
        }
    }

    private function generateInterpretasiPerbandinganKinerja($persentil)
    {
        if ($persentil > 90) {
            return "Kinerja siswa ini berada di atas rata-rata kelas.";
        } else if ($persentil > 75) {
            return "Kinerja siswa ini di atas rata-rata kelas.";
        } else if ($persentil > 50) {
            return "Kinerja siswa ini sesuai dengan rata-rata kelas.";
        } else {
            return "Kinerja siswa ini berada di bawah rata-rata kelas.";
        }
    }

    private function generateSaran($persentil, $tingkatKemampuan, $analisisSoal, $jawabanBenarCount, $totalPesertaYangMendapatSoal, $indeksDiskriminasi, $riwayatKinerja)
    {

        $indeksKesulitan = ($totalPesertaYangMendapatSoal - $jawabanBenarCount) / $totalPesertaYangMendapatSoal;
        $saran = "Saran: ";

        // 1. Saran Berdasarkan Perbandingan Kinerja:
        if ($persentil < 50) {
            $saran .= "Kamu bisa meningkatkan kinerja kamu dengan fokus pada beberapa area:";
            // Tambahkan saran berdasarkan persentil
            if ($tingkatKemampuan < 0.5) {
                $saran .= "  Pertimbangkan untuk mendapatkan bantuan dari guru atau tutor, atau mengerjakan lebih banyak latihan." . PHP_EOL;
            }
        } else if ($persentil < 75) {
            $saran .= "Teruskan upaya belajar yang baik! Kamu  bisa  mencoba  untuk  meningkatkan  kemampuan   Anda  lebih   lanjut   dengan   [saran   spesifik  berdasarkan   analisis]. ";
        } else {
            $saran .= "Kamu  telah   menunjukkan   potensi   yang   baik.   Pertahankan   upaya   belajar   dan    mencari   tantangan   baru!" . PHP_EOL;
        }

        // 2. Saran Berdasarkan Tingkat Kemampuan
        if ($tingkatKemampuan < 0.5) {
            $saran .= "Kamu perlu mengulang dan  mengerti  konsep  dasar  dari  mata  pelajaran  ini.  ";
            $saran .= "Pertimbangkan  untuk  mencari  sumber  belajar  lainnya   yang  lebih  mudah   dipahami.  " . PHP_EOL;
        } else if ($tingkatKemampuan > 1) {
            $saran .= "Kamu menunjukkan  penguasaan  yang  sangat  baik.  Cobalah  untuk   mencari  tantangan   baru   seperti  latihan  yang  lebih  sulit  atau  topik  yang  lebih   kompleks. ";
        }

        // 3. Saran Berdasarkan Riwayat Kinerja
        if (count($riwayatKinerja) > 0) {
            $saranRiwayat = "Kamu  telah  melakukan  tes  sebelumnya. ";

            $lastScore = 0;
            $lastTimestamp = 0;
            $hasImprovement = true;

            // Mengurutkan riwayat kinerja berdasarkan tanggal, descending
            $riwayat = $riwayatKinerja->sortByDesc('created_at');

            // Mengolah riwayat untuk menemukan tren
            foreach ($riwayat as $index => $history) {
                // Mendapatkan skor terakhir dan timestamp-nya
                $lastScore = $history->skor_terkalibrasi; // Asumsikan data riwayat sudah memiliki skor terkalibrasi
                $lastTimestamp = strtotime($history->created_at);

                // Jika ini bukan riwayat pertama
                if ($index > 0) {
                    $previousTimestamp = strtotime($riwayat[$index - 1]->created_at);
                    $previousScore = $riwayat[$index - 1]->skor_terkalibrasi;

                    if ($previousScore > $lastScore) {
                        $hasImprovement = false; // Ada penurunan
                        break;
                    }
                }
            }

            if ($hasImprovement) {
                $saranRiwayat .= "Kamu  menunjukkan  penguasaan  yang  bagus.  ";
            } else {
                $saranRiwayat .= "Kamu  menunjukkan  penguasaan  yang  kurang  bagus.  ";
            }
            $saranRiwayat .= "Cobalah untuk  mencari  tantangan   baru   seperti  latihan  yang  lebih  sulit   atau  topik  yang  lebih   kompleks. ";
            $saran .= $saranRiwayat;
        }

        return trim($saran); // Hapus spasi ekstra

    }

}
