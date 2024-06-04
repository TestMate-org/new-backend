<?php

namespace App\Http\Controllers\Api\v2;

use App\Actions\SendResponse;
use App\Http\Controllers\Controller;
use App\Jadwal;
use App\JawabanPeserta;
use App\Models\UjianConstant;
use App\Services\Ujian\BenarSalahService;
use App\Services\Ujian\EsayService;
use App\Services\Ujian\IsianSingkatService;
use App\Services\Ujian\JawabanPesertaService;
use App\Services\Ujian\MengurutkanService;
use App\Services\Ujian\MenjodohkanService;
use App\Services\Ujian\PilihanGandaKomplekService;
use App\Services\Ujian\PilihanGandaService;
use App\Services\Ujian\SetujuTidakService;
use App\Soal;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use TestMate\Services\Ujian\UjianService;

/**
 * UjianController
 *
 * @author TestMate <dev@testmate.org>
 * @since 0.0.1 <risol>
 */
class UjianController extends Controller
{
    /**
     * @Route(path="api/v2/ujian", methods={"POST"})
     *
     * Simpan/Update jawaban siswa pada ujian aktif
     *
     * @param Request $request
     * @param UjianService $ujianService
     * @param JawabanPesertaService $jawabanPesertaService
     * @return Response
     * @throws Exception
     */
    public function store(Request $request, UjianService $ujianService, JawabanPesertaService $jawabanPesertaService)
    {
        $request->validate([
            'jawaban_id' => 'required',
            'index' => 'required',
        ]);

        $peserta = request()->get('peserta-auth');

        $find = $jawabanPesertaService->getJawaban($request->jawaban_id);
        $soal = Soal::find($find->soal_id);

        if (!$find) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_ANSWER_FOUND);
        }

        # ambil data siswa ujian
        # yang sedang dikerjakan pada hari ini
        # yang mana jadwal tersebut sedang aktif dan tanggal pengerjaannya hari ini
        $ujian = $ujianService->onProgressToday($peserta->id);

        // #Calculate IRT
        // #Calculate IRT
        // #Calculate IRT
        // function calculate3PLProbability($theta, $a, $b, $c)
        // {
        //     $e = exp(-$a * ($theta - $b));
        //     $p = $c + (1 - $c) / (1 + $e);
        //     return $p;
        // }

        // Parameters for question
        $a = $soal->a; // Discrimination
        $b = $soal->b; // Difficulty
        $c = $soal->c; // Guessing

        // Data awal (contoh)
        $responses = [
            [-1.0, 1],
            [0.0, 0],
            [1.0, 1],
            // Tambahkan data lainnya sesuai kebutuhan
        ];

        // Responses is only theta_akhir and is_correct and to array
        $responses = JawabanPeserta::where('jadwal_id', $find->jadwal_id)
            ->get(['theta_akhir', 'iscorrect'])
            ->map(function ($response) {
                return [(float) $response->theta_akhir, (int) $response->is_correct];
            })
            ->toArray();

        $responses = $responses ?: [];

        // Kalibrasi parameter
        list($calibrated_a, $calibrated_b, $calibrated_c) = $this->calibrateIRT($a, $b, $c, $responses);

        // Assuming an initial ability level for Andi
        $lastThetaStudentAbility = JawabanPeserta::where('peserta_id', $peserta->id)
            ->where('jadwal_id', $find->jadwal_id)
            ->orderBy('created_at', 'desc')
            ->first()
            ->theta_akhir;

        $studentAbility = $lastThetaStudentAbility ? $lastThetaStudentAbility : 0;
//
        $theta_values = JawabanPeserta::where('peserta_id', $peserta->id)
            ->where('jadwal_id', $find->jadwal_id)
            ->pluck('theta_akhir')
            ->toArray();

        $probabilities = array_map(function ($theta) use ($a, $b, $c) {
            return $this->irtProbability($a, $b, $c, $theta);
        }, $theta_values);

        // $information_values = array_map(function ($p) use ($a, $c) {
        //     return $this->itemInformation($a, $p, $c);
        // }, $probabilities);
        //

        // Calculate the probability of Andi answering correctly
        // $thetaAkhir = calculate3PLProbability($studentAbility, $calibrated_a, $calibrated_b, $calibrated_c);
        $jawabanPeserta = JawabanPeserta::findOrFail($find->id);

        $countHowMuchSoalAlreadyShow = JawabanPeserta::where([
            'peserta_id' => $peserta->id,
            'jadwal_id' => $jawabanPeserta->jadwal_id,
            'banksoal_id' => $jawabanPeserta->banksoal_id,
        ])->count();

        $jawabanPeserta->update([
            'theta_akhir' => $theta_values[0],
            // 'probabilities' => $probabilities[0],
            // 'information_values' => $information_values[0],
            'urutan' => $countHowMuchSoalAlreadyShow,
        ]);

        $soal = Soal::find($find->soal_id);
        $soal->update([
            'a_calibrated' => $calibrated_a,
            'b_calibrated' => $calibrated_b,
            'c_calibrated' => $calibrated_c,
        ]);

        #Calculate IRT
        #Calculate IRT
        #Calculate IRT

        if (!$ujian) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_UJIAN_FOUND);
        }

        # kurangi waktu ujian
        $ujianService->updateReminingTime($ujian);

        # Jika yang dikirimkan adalah esay
        if (isset($request->essy)) {
            return EsayService::setJawab($request, $find);
        }

        # Jika yang dikirimkan adalah isian singkat
        if (isset($request->isian)) {
            return IsianSingkatService::setJawab($request, $find);
        }

        # Jika yang dikirimkan adalah jawaban komleks
        if (is_array($request->jawab_complex)) {
            return PilihanGandaKomplekService::setJawab($request, $find);
        }

        # Jika yang dikirimkan adalah menjodohkan
        if (isset($request->menjodohkan)) {
            return MenjodohkanService::setJawab($request, $find);
        }

        # Jika yang dikirimkan adalah mengurutkan
        if (isset($request->mengurutkan)) {
            return MengurutkanService::setJawab($request, $find);
        }

        # jika yang dikirimkan adalah salah/benar
        if (is_array($request->benar_salah)) {
            return BenarSalahService::setJawab($request, $find);
        }

        # Jika yang dikirimkan adalah setuju/tidak
        if (isset($request->setuju_tidak)) {
            return SetujuTidakService::setJawab($request, $find);
        }

        # Jika yang dikirimkan adalah pilihan ganda
        return PilihanGandaService::setJawab($request, $find);

    }

    private function irtProbability($a, $b, $c, $theta)
    {
        return $c + (1 - $c) / (1 + exp(-$a * ($theta - $b)));
    }

    private function calibrateIRT($a, $b, $c, $responses)
    {
        $learning_rate = 0.01; // Kecepatan belajar untuk pembaruan parameter
        $iterations = 100; // Jumlah iterasi

        // Inisialisasi parameter a, b, dan c
        // $a = 1.0;
        // $b = 0.0;
        // $c = 0.0;

        for ($i = 0; $i < $iterations; $i++) {
            $a_grad = 0;
            $b_grad = 0;
            $c_grad = 0;

            foreach ($responses as $response) {
                $theta = $response[0];
                $observed = $response[1];
                $expected = $this->irtProbability($a, $b, $c, $theta);

                $a_grad += ($observed - $expected) * $expected * (1 - $expected) * ($theta - $b);
                $b_grad += -($observed - $expected) * $expected * (1 - $expected) * $a;
                $c_grad += ($observed - $expected) * (1 - $expected);
            }

            // Pembaruan parameter
            $a += $learning_rate * $a_grad;
            $b += $learning_rate * $b_grad;
            $c += $learning_rate * $c_grad;
        }

        return [$a, $b, $c];
    }

    private function itemInformation($a, $p, $c)
    {
        return ($a ** 2 * (1 - $p) * ($p - $c) * ($p - $c)) / ($p * (1 - $c) * (1 - $c));
    }

    /**
     * @Route(path="api/v2/ujian/ragu-ragu", methods={"POST"})
     *
     * Set ragu ragu in siswa
     *
     * @param Request $request
     * @param UjianService $ujianService
     * @param JawabanPesertaService $jawabanPesertaService
     * @return Response
     * @throws Exception
     */
    public function setRagu(Request $request, UjianService $ujianService, JawabanPesertaService $jawabanPesertaService)
    {
        $peserta = request()->get('peserta-auth');

        $find = $jawabanPesertaService->getJawaban($request->jawaban_id);

        if (!$find) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_ANSWER_FOUND);
        }

        if (!isset($request->ragu_ragu)) {
            return SendResponse::acceptCustom([
                'data' => ['jawab' => $find->jawab],
                'index' => $request->index,
            ]);
        }

        # ambil data siswa ujian
        # yang sedang dikerjakan pada hari ini
        # yang mana jadwal tersebut sedang aktif dan tanggal pengerjaannya hari ini
        $ujian = $ujianService->onProgressToday($peserta->id);

        if (!$ujian) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_UJIAN_FOUND);
        }

        # update sisa waktu ujian
        $ujianService->updateReminingTime($ujian);

        try {
            DB::table('jawaban_pesertas')
                ->where('id', $find->id)
                ->update([
                    'ragu_ragu' => $request->ragu_ragu,
                ]);
        } catch (Exception $e) {
            return SendResponse::internalServerError('Terjadi kesalahan 500. ' . $e->getMessage());
        }

        return SendResponse::acceptCustom(['data' => ['jawab' => $find->jawab], 'index' => $request->index]);
    }

    /**
     * @Route(path="api/v2/ujian/selesai", methods={"GET"})
     *
     * Selesaikan ujian
     *
     * @param UjianService $ujianService
     * @return Response
     */
    public function selesai(UjianService $ujianService)
    {
        $peserta = request()->get('peserta-auth');

        # ambil data siswa ujian
        # yang sedang dikerjakan pada hari ini
        # yang mana jadwal tersebut sedang aktif dan tanggal pengerjaannya hari ini
        $ujian = $ujianService->onProgressToday($peserta->id);

        if (!$ujian) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_UJIAN_FOUND);
        }

        # Cek apakah hasil ujian pernah di generate sebelumnya
        $hasilUjian = DB::table('hasil_ujians')
            ->where([
                'peserta_id' => $peserta->id,
                'jadwal_id' => $ujian['jadwal_id'],
            ])
            ->count();

        if ($hasilUjian > 0) {
            try {
                DB::table('siswa_ujians')
                    ->where('id', $ujian['id'])
                    ->update([
                        'status_ujian' => UjianConstant::STATUS_FINISHED,
                    ]);

                return SendResponse::badRequest(UjianConstant::WARN_UJIAN_HAS_FINISHED_BEFORE);
            } catch (Exception $e) {
                return SendResponse::internalServerError('Terjadi kesalahan 500. ' . $e->getMessage());
            }
        }

        # validate minimum time
        $start = Carbon::createFromFormat('H:i:s', $ujian['mulai_ujian_shadow']);
        $now = Carbon::createFromFormat('H:i:s', Carbon::now()->format('H:i:s'));
        $diff_in_minutes = $start->diffInSeconds($now);

        $jadwal = DB::table("jadwals")->where("id", $ujian['jadwal_id'])->first();
        if ($diff_in_minutes < ($jadwal->min_test * 60)) {
            return SendResponse::badRequest(UjianConstant::MINUMUM_TEST_INVALID . " min:" . $jadwal->min_test . " menit");
        }

        # ambil hanya banksoal untuk jawaban peserta pertama
        $jawaban = DB::table('jawaban_pesertas')
            ->where([
                'jadwal_id' => $ujian['jadwal_id'],
                'peserta_id' => $peserta->id,
            ])
            ->select('banksoal_id')
            ->first();

        if (!$jawaban) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_UJIAN_FOUND);
        }

        try {
            DB::beginTransaction();

            $ujianService->finishing($jawaban->banksoal_id, $ujian['jadwal_id'], $peserta->id, $ujian['id']);

            DB::table('siswa_ujians')
                ->where('id', $ujian['id'])
                ->update([
                    'status_ujian' => UjianConstant::STATUS_FINISHED,
                    'selesai_ujian' => now()->format('H:i:s'),
                ]);
            DB::commit();

            return SendResponse::accept('ujian berhasil diselesaikan');
        } catch (Exception $e) {
            DB::rollback();
            return SendResponse::internalServerError('Terjadi kesalahan 500. ' . $e->getMessage());
        }
    }

    /**
     * Fungsi untuk memilih butir selanjutnya dalam CAT berdasarkan IRT 3PL
     * @param int $jawabanPesertaId ID sesi ujian peserta
     * @return Question Butir selanjutnya
     */
    public function getNextQuestion(Request $request, UjianService $ujianService, JawabanPesertaService $jawabanPesertaService)
    {
        $peserta = request()->get('peserta-auth');
        $bank_soal_id = $request->bank_soal_id;
        $banksoal = DB::table('banksoals')
            ->where('id', $bank_soal_id)
            ->first();

        $jawaban_id = $request->jawaban_id;

        $ujian_siswa = $ujianService->onProgressToday($peserta->id);
        if (!$ujian_siswa) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_UJIAN_FOUND);
        }

        $jawabanPeserta = JawabanPeserta::findOrFail($jawaban_id);
        $jawabanPesertaNow = $jawabanPeserta;
        $jadwal = Jadwal::findOrFail($jawabanPeserta->jadwal_id);

        # Ambil setting dari jadwal
        $setting = $jadwal->setting;

        $soal_pg = PilihanGandaService::getSoalNext($peserta, $banksoal, $jadwal, $jawabanPesertaNow);

        # Gabungkan semua collection dari tipe soal
        $soals = [];
        $list = collect([
            '1' => $soal_pg,
        ]);
        foreach ($setting['list'] as $value) {
            $soal = $list->get($value['id']);
            if ($soal) {
                $soals = array_merge($soals, $soal);
            }
        }

        $new_soals = [];
        $time_offset = 1;
        foreach ($soals as $key => $soal) {
            $new_soals[$key] = $soal;
            $new_soals[$key]['created_at'] = now()->addSeconds($time_offset);

            $time_offset++;
        }

        # Insert ke database sebagai jawaban siswa
        try {
            DB::beginTransaction();
            DB::table('jawaban_pesertas')->insert($new_soals);
            DB::commit();
        } catch (Exception $e) {
            DB::rollBack();
            return SendResponse::internalServerError($e->getMessage());
        }

        # Ambil jawaban siswa
        $jawaban_peserta = $ujianService->pesertaAnswersNext(
            $jadwal->id,
            $peserta->id,
            $setting['acak_opsi'],
            $bank_soal_id,
            $soal_pg[0]['soal_id']
        );

        return SendResponse::acceptCustom(['data' => $jawaban_peserta, 'detail' => $ujian_siswa]);

    }

    /**
     * Fungsi untuk menghitung dan memperbarui estimasi kemampuan peserta
     * @param int $sessionId ID sesi ujian peserta
     * @param array $responses Array respons terhadap butir soal
     */
    public function updateAbilityEstimate($sessionId, $responses)
    {
        // Implementasi perhitungan estimasi kemampuan berdasarkan model IRT 3PL
        // Ini melibatkan logika kompleks dan mungkin integrasi dengan perangkat lunak statistik

        // Contoh sederhana: perbarui kemampuan berdasarkan jumlah jawaban benar
        $correctCount = count(array_filter($responses, function ($response) {
            return $response['is_correct']; // Assumsi 'is_correct' menandakan kebenaran jawaban
        }));

        $newAbility = $correctCount / count($responses); // Placeholder untuk logika sebenarnya
        JawabanPeserta::where('id', $sessionId)->update(['ability' => $newAbility]);
    }

}
