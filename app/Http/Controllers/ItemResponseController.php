<?php

namespace App\Http\Controllers;

use App\JawabanPeserta;
use App\Soal;
use Illuminate\Support\Facades\DB;

class ItemResponseController extends Controller
{
    // Fungsi untuk menghitung probabilitas jawaban benar menggunakan model 3PL
    public function calculateItemResponse3PL($theta, $a, $b, $c)
    {
        $e = exp(1); // Basis logaritma natural e
        return $c + (1 - $c) / (1 + pow($e, -1 * $a * ($theta - $b)));
    }

    // Fungsi untuk menghasilkan data IRF untuk 20 soal dan 10 siswa
    public function generateIRFData()
    {
        $students = range(1, 5);
        $items = range(1, 2);

        $data = [];

        foreach ($students as $student) {
            foreach ($items as $item) {
                // Contoh penggunaan parameter item yang di-hardcode
                $a = rand(5, 15) / 10; // Diskriminasi acak antara 0.5 dan 1.5
                $b = rand(-20, 20) / 10; // Kesulitan acak antara -2.0 dan 2.0
                $c = 0.25; // Tebakan tetap

                // Menghitung θ secara acak untuk setiap siswa sebagai contoh
                $theta = rand(-30, 30) / 10;

                $probability = $this->calculateItemResponse3PL($theta, $a, $b, $c);
                $data[] = [
                    'student' => $student,
                    'item' => $item,
                    'a' => $a,
                    'b' => $b,
                    'c' => $c,
                    'theta' => $theta,
                    'probability' => $probability,
                ];
            }
        }

        return response()->json($data);
    }

    public function showIRFGraph()
    {
        $data = $this->generateIRFData();

        return view('item_response_graph', compact('data'));
    }

    public function getIrtChart($soalId)
    {
        $soal = Soal::find($soalId);

        if (!$soal) {
            return response()->json(['error' => 'Item tidak ditemukan'], 404);
        }

        // 1. Hitung jumlah jawaban benar
        $jawabanBenarCount = JawabanPeserta::where('soal_id', $soalId)
            ->where('iscorrect', true)
            ->count();

        $totalPesertaYangMendapatSoal = JawabanPeserta::where('soal_id', $soalId)
            ->count();

        // 2. Hitung persentase jawaban benar
        $jawabanBenarPersentase = ($jawabanBenarCount / $totalPesertaYangMendapatSoal) * 100;

        // 3. Hitung indeks kesulitan
        $indeksKesulitan = ($totalPesertaYangMendapatSoal - $jawabanBenarCount) / $totalPesertaYangMendapatSoal;

        // 4. Hitung indeks diskriminasi (metode Upper-Lower 27%)
        $indeksDiskriminasi = $this->calculateDiskriminasiUpperLower($soal);
        // return $indeksDiskriminasi;
        // Update atribut soal
        // $soal->jawaban_benar_count = $jawabanBenarCount;
        // $soal->jawaban_benar_persentase = $jawabanBenarPersentase;
        // $soal->indeks_kesulitan = $indeksKesulitan;
        // $soal->indeks_diskriminasi = $indeksDiskriminasi;
        // $soal->save();

        // return response()->json([
        //     'jawaban_benar_count' => $jawabanBenarCount,
        //     'total_peserta_yang_mendapat_soal' => $totalPesertaYangMendapatSoal,
        //     'jawaban_benar_persentase' => $jawabanBenarPersentase,
        //     'indeks_kesulitan' => $indeksKesulitan,
        //     'indeks_diskriminasi' => $indeksDiskriminasi,
        // ]);

        $irtData = $this->calculateIRTData($soal); // Hitung data IRT

        // Tambahkan deskripsi berdasarkan data IRT
        $irtData['iccDescription'] = $this->generateICCDescription($soal->a_calibrated, $soal->b_calibrated, $soal->c_calibrated);
        $irtData['iifDescription'] = $this->generateIIFDescription($soal->a_calibrated, $soal->b_calibrated, $soal->c_calibrated);
        $irtData['itemSuggestion'] = $this->generateItemSuggestion($soal->a_calibrated, $soal->b_calibrated, $soal->c_calibrated);
        $irtData['jawaban_benar_count'] = $jawabanBenarCount;
        $irtData['total_peserta_yang_mendapat_soal'] = $totalPesertaYangMendapatSoal;
        $irtData['jawaban_benar_persentase'] = $jawabanBenarPersentase;
        $irtData['indeks_kesulitan'] = $indeksKesulitan;
        $irtData['indeks_diskriminasi'] = $indeksDiskriminasi;

        return response()->json($irtData);

    }

    public function calculateSoalStats(Request $request, $soalId)
    {
        $soal = Soal::findOrFail($soalId);

    }

    private function calculateDiskriminasiUpperLower(Soal $soal)
    {
        // Mendapatkan skor peserta untuk soal ini
        $skorPeserta = JawabanPeserta::where('soal_id', $soal->id)
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
        $persentaseAtas = JawabanPeserta::where('soal_id', $soal->id)
            ->whereIn('peserta_id', $skorPeserta->pluck('peserta_id'))
            ->where('iscorrect', true)
            ->count() / $kelompokAtas * 100;

        $persentaseBawah = JawabanPeserta::where('soal_id', $soal->id)
            ->whereIn('peserta_id', $skorPeserta->pluck('peserta_id'))
            ->where('iscorrect', true)
            ->count() / $kelompokBawah * 100;

        // return $persentaseBawah;
        // Menghitung indeks diskriminasi
        $indeksDiskriminasi = $persentaseAtas - $persentaseBawah;

        return $indeksDiskriminasi;
    }

    private function generateICCDescription($a, $b, $c)
    {
        $iccDescription = "";

        // Daya Pembeda
        if ($a > 1) {
            $iccDescription .= "Item ini memiliki daya pembeda yang tinggi. ";
        } else if ($a > 0.5) {
            $iccDescription .= "Item ini memiliki daya pembeda yang sedang. ";
        } else {
            $iccDescription .= "Item ini memiliki daya pembeda yang rendah. ";
        }

        // Kesulitan Item
        if ($b < 0) {
            $iccDescription .= "Item ini relatif mudah. ";
        } else if ($b > 1) {
            $iccDescription .= "Item ini relatif sulit. ";
        } else {
            $iccDescription .= "Item ini memiliki tingkat kesulitan sedang. ";
        }

        // Parameter c
        if ($c > 0.25) {
            $iccDescription .= "Parameter c menunjukkan bahwa item ini memiliki kemungkinan tebakan yang tinggi. ";
        } else {
            $iccDescription .= "Parameter c menunjukkan bahwa item ini memiliki kemungkinan tebakan yang rendah. ";
        }

        return $iccDescription;
    }

    private function generateIIFDescription($a, $b, $c)
    {
        $iifDescription = "";

        // Informasi Maksimum
        if ($a > 1) {
            $iifDescription .= "Item ini memberikan informasi yang sangat baik untuk peserta dengan kemampuan di sekitar nilai b. ";
        } else if ($a > 0.5) {
            $iifDescription .= "Item ini memberikan informasi yang cukup baik untuk peserta dengan kemampuan di sekitar nilai b. ";
        } else {
            $iifDescription .= "Item ini memberikan informasi yang terbatas untuk peserta dengan kemampuan di sekitar nilai b. ";
        }

        // Rentang Informasi
        if ($a > 1.5) {
            $iifDescription .= "Item ini memberikan informasi yang bermanfaat untuk berbagai tingkat kemampuan. ";
        } else if ($a > 0.5) {
            $iifDescription .= "Item ini memberikan informasi yang terutama bermanfaat untuk peserta dengan kemampuan di sekitar nilai b. ";
        }

        return $iifDescription;
    }

    private function generateItemSuggestion($a, $b, $c)
    {
        $suggestions = "";

        // Parameter a (Daya Pembeda)
        if ($a < 0.3) {
            $suggestions .= "Item ini memiliki daya pembeda yang sangat rendah. Pertimbangkan untuk mengganti item ini dengan item yang memiliki daya pembeda lebih tinggi. ";
        } else if ($a < 0.7) {
            $suggestions .= "Item ini memiliki daya pembeda yang sedang. Pertimbangkan untuk menambahkan pilihan jawaban yang lebih menantang. ";
        }

        // Parameter b (Kesulitan Item)
        if ($b < -0.5) {
            $suggestions .= "Item ini relatif mudah. Pertimbangkan untuk menambahkan pilihan jawaban yang lebih menantang. ";
        } else if ($b > 0.5) {
            $suggestions .= "Item ini relatif sulit. Pertimbangkan untuk mengubah rumusan pertanyaan agar lebih mudah dipahami. ";
        }

        // Parameter c (Probabilitas Tebakan)
        if ($c > 0.4) {
            $suggestions .= "Item ini memiliki kemungkinan tebakan yang tinggi. Pertimbangkan untuk mengubah format soal atau merevisi pertanyaan dan pilihan jawaban. ";
        } else if ($c < 0.2) {
            $suggestions .= "Item ini mungkin terlalu sulit atau tidak memiliki pilihan jawaban yang jelas.  Pertimbangkan untuk mengubah pertanyaan atau pilihan jawaban. ";
        }

        return $suggestions;
    }

    // private function generateICCDescription($a, $b, $c)
    // {
    //     // Logika untuk menghasilkan deskripsi ICC berdasarkan nilai a, b, dan c
    //     // Contoh:
    //     if ($a > 1) {
    //         return "Item ini memiliki daya pembeda yang tinggi, menunjukkan sensitivitas yang baik terhadap perbedaan kemampuan.";
    //     } else {
    //         return "Item ini memiliki daya pembeda yang sedang, menunjukkan sensitivitas yang cukup terhadap perbedaan kemampuan.";
    //     }
    // }

    // private function generateIIFDescription($a, $b, $c)
    // {
    //     // Logika untuk menghasilkan deskripsi IIF berdasarkan nilai a, b, dan c
    //     // Contoh:
    //     if ($a > 1 && $b < 0) {
    //         return "Item ini memberikan informasi yang baik untuk peserta dengan kemampuan di sekitar nilai b.";
    //     } else {
    //         return "Item ini memberikan informasi yang moderat untuk berbagai tingkat kemampuan.";
    //     }
    // }

    public function calculateIRTData($item)
    {
        // 1. Ambil parameter item (a, b, c)
        $a = $item->a_calibrated;
        $b = $item->b_calibrated;
        $c = $item->c_calibrated;

        // 2. Hitung nilai theta (misalnya, dari -3 hingga 3 dengan interval 0.1)
        $theta_values = range(-3, 3, 1);

        // 3. Hitung probabilitas (ICC) untuk setiap nilai theta
        $probabilities = array_map(function ($theta) use ($a, $b, $c) {
            return round($this->irtProbability($a, $b, $c, $theta), 2); // Round to 2 decimal places
        }, $theta_values);

        // 4. Hitung informasi item (IIF) untuk setiap nilai theta
        $information_values = array_map(function ($theta) use ($a, $b, $c) {
            return round($this->itemInformation($a, $b, $c, $theta), 2); // Round to 2 decimal places
        }, $theta_values);

        // 5. Kembalikan data IRT
        return [
            'theta_values' => $theta_values,
            'probabilities' => $probabilities,
            'information_values' => $information_values,
            'a' => $a,
            'b' => $b,
            'c' => $c,
        ];
    }

    private function irtProbability($a, $b, $c, $theta)
    {
        // Rumus ICC untuk model 3 parameter logistik
        return $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)));
    }

    private function itemInformation($a, $b, $c, $theta)
    {
        // Rumus IIF untuk model 3 parameter logistik
        $probability = $this->irtProbability($a, $b, $c, $theta);
        return ($a ** 2) * ($probability * (1 - $probability)) / ((1 - $c) ** 2);
    }

    // // Fungsi untuk menghitung probabilitas (ICC)
    // private function irtProbability($a, $b, $c, $theta)
    // {
    //     // Implementasikan rumus ICC berdasarkan model IRT yang Anda gunakan
    //     // Misalnya, untuk model 3 parameter logistik:
    //     return $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)));
    // }

    // // Fungsi untuk menghitung informasi item (IIF)
    // private function itemInformation($a, $b, $c, $theta)
    // {
    //     // Implementasikan rumus IIF berdasarkan model IRT yang Anda gunakan
    //     // Misalnya, untuk model 3 parameter logistik:
    //     $probability = $this->irtProbability($a, $b, $c, $theta);
    //     return ($a ** 2) * ($probability * (1 - $probability)) / ((1 - $c) ** 2);
    // }

}
