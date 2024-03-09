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

        if (!$find) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_ANSWER_FOUND);
        }

        # ambil data siswa ujian
        # yang sedang dikerjakan pada hari ini
        # yang mana jadwal tersebut sedang aktif dan tanggal pengerjaannya hari ini
        $ujian = $ujianService->onProgressToday($peserta->id);

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

        // $index = $request->index;
        $jawaban_id = $request->jawaban_id;
        // $soal_id = $request->soal_id;
        // $user_id = $request->user_id;

        $ujian_siswa = $ujianService->onProgressToday($peserta->id);
        if (!$ujian_siswa) {
            return SendResponse::badRequest(UjianConstant::NO_WORKING_UJIAN_FOUND);
        }

        $jawabanPeserta = JawabanPeserta::findOrFail($jawaban_id);
        // $currentAbility = $jawabanPeserta->b ?? 0;
        $jadwal = Jadwal::findOrFail($jawabanPeserta->jadwal_id);

        # Ambil setting dari jadwal
        $setting = $jadwal->setting;

        $soal_pg = PilihanGandaService::getSoalNext($peserta, $banksoal, $jadwal);

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

        // $ujian = DB::table('jadwals')
        //     ->where('id', $jawabanPeserta->jadwal_id)
        //     ->first();
        // $setting = json_decode($ujian->setting, true);

        // $soalAlreadyAnsweredIds = JawabanPeserta::where([
        //     'peserta_id' => $user_id,
        //     'jadwal_id' => $jawabanPeserta->jadwal_id,
        //     'banksoal_id' => $bank_soal_id,
        // ])->pluck('soal_id')->toArray();

        // $pg = DB::table('soals')
        //     ->whereNotIn('id', $soalAlreadyAnsweredIds)
        //     ->where([
        //         'banksoal_id' => $bank_soal_id,
        //         'tipe_soal' => SoalConstant::TIPE_PG,
        //     ]);

        // $pg = $pg->inRandomOrder();

        // # Ambil soal sebanyak maximum
        // $pg = $pg->take(1)->get();

        // $soal_pg = [];
        // foreach ($pg as $k => $item) {
        //     $soal = Soal::find($item->id)
        //         ->whereNotIn('id', $soalAlreadyAnsweredIds)
        //         ->select(
        //             'audio',
        //             'banksoal_id',
        //             'direction',
        //             'id',
        //             'pertanyaan',
        //             'tipe_soal',
        //             'layout',
        //         )
        //         ->first()->toArray();

        //     $jawabans = JawabanSoal::where('soal_id', $item->id)
        //         ->select([
        //             'id',
        //             'text_jawaban',
        //             'label_mark',
        //         ])
        //         ->get()
        //         ->toArray();
        //     $soal['jawabans'] = $jawabans;
        //     array_push($soal_pg, [
        //         'id' => Str::uuid()->toString(),
        //         'peserta_id' => $user_id,
        //         'banksoal_id' => $bank_soal_id,
        //         'soal_id' => $item->id,
        //         'soal' => $soal,
        //         // 'jawaban' => $jawabans,
        //         'jawab' => "0",
        //         // 'b' => $currentAbility,
        //         'iscorrect' => "0",
        //         'jadwal_id' => $jawabanPeserta->jadwal_id,
        //         'ragu_ragu' => "0",
        //         'esay' => '',
        //     ]);
        // }

        // // # Gabungkan semua collection dari tipe soal
        // $soals = [];
        // $list = collect([
        //     '1' => $soal_pg,
        // ]);
        // // $soals = $list->collapse();
        // foreach ($setting['list'] as $value) {
        //     $soal = $list->get($value['id']);
        //     if ($soal) {
        //         $soals = array_merge($soals, $soal);
        //     }
        // }

        // $new_soals = [];
        // $time_offset = 1;
        // foreach ($soals as $key => $soal) {
        //     $new_soals[$key] = $soal;
        //     $new_soals[$key]['created_at'] = now()->addSeconds($time_offset);

        //     $time_offset++;
        // }

        // # Insert ke database sebagai jawaban siswa
        // try {
        //     DB::beginTransaction();
        //     // var_dump($new_soals);
        //     // exit;
        //     DB::table('jawaban_pesertas')->insert($new_soals);
        //     DB::commit();
        // } catch (Exception $e) {
        //     DB::rollBack();
        //     return SendResponse::internalServerError($e->getMessage());
        // }

        // foreach ($list as $item) {
        //     // $jawabans = $item->soal->jawabans;
        // }

        // $jawabans = $item->soal->jawabans;
        // var_dump($item);
        // exit;

        // $result[] = [
        //     'id' => $item->id,
        //     'banksoal_id' => $item->banksoal_id,
        //     'soal_id' => $item->soal_id,
        //     'jawab' => $item->jawab,
        //     'esay' => $item->esay,
        //     'jawab_complex' => json_decode($item->jawab_complex),
        //     'benar_salah' => $item->benar_salah,
        //     'setuju_tidak' => $item->setuju_tidak,
        //     'answered' => $item->answered,
        //     'soal' => [
        //         'audio' => $item->soal->audio,
        //         'banksoal_id' => $item->soal->banksoal_id,
        //         'direction' => $item->soal->direction,
        //         'id' => $item->soal->id,
        //         'jawabans' => $jawabans,
        //         'pertanyaan' => $item->soal->pertanyaan,
        //         'tipe_soal' => intval($item->soal->tipe_soal),
        //         'layout' => intval($item->soal->layout),
        //     ],
        //     'ragu_ragu' => $item->ragu_ragu,
        // ];

        // var_dump($result);
        // exit;

        return SendResponse::acceptCustom(['data' => $new_soals, 'detail' => $ujian_siswa]);

        // $nextQuestion = Soal::whereNotIn('id', $soalAlreadyAnsweredIds)
        //     ->where('banksoal_id', $bank_soal_id)
        //     ->where('tingkat_kesulitan', '>', $currentAbility)
        //     ->pluck([
        //         'id',
        //         'banksoal_id',
        //         'pertanyaan',
        //         'tipe_soal',
        //         'audio',
        //         'direction',
        //         'layout',
        //     ])
        //     ->get();

        // $nextQuestion = DB::table('soals')
        //     ->whereNotIn('id', $soalAlreadyAnsweredIds)
        //     ->select([
        //         'id',
        //         'banksoal_id',
        //         'pertanyaan',
        //         'tipe_soal',
        //         'audio',
        //         'direction',
        //         'layout',
        //     ])
        //     ->limit(1)
        //     ->get();

        // if (!$nextQuestion) {
        //     $nextQuestion = Soal::whereNotIn('id', $soalAlreadyAnsweredIds)
        //         ->where('banksoal_id', $bank_soal_id)
        //         ->orderBy('tingkat_kesulitan', 'asc')
        //         ->select([
        //             'id',
        //             'banksoal_id',
        //             'pertanyaan',
        //             'tipe_soal',
        //             'audio',
        //             'direction',
        //             'layout',
        //         ])
        //         ->get();
        // }

        // $soal_jawabans = DB::table('jawaban_soals')
        //     ->whereIn('soal_id', $nextQuestion->pluck('id')->toArray());

        // $soal_jawabans = $soal_jawabans->inRandomOrder();

        // $soal_jawabans = $soal_jawabans->get();
        // $soal_jawabans_indexeds = $soal_jawabans->groupBy('soal_id');

        // $item = new stdClass;
        // $nextQuestion = $nextQuestion->map(function ($item) use ($soal_jawabans_indexeds) {
        //     $item->jawabans = $soal_jawabans_indexeds->get($item->id, new Collection())->values();
        //     return $item;
        // });
        // var_dump($item);
        // exit;
        // $result[] = [
        //     'id' => $item->id,
        //     'banksoal_id' => $item->banksoal_id,
        //     'soal_id' => $item->soal_id,
        //     'jawab' => $item->jawab,
        //     'esay' => $item->esay,
        //     'jawab_complex' => json_decode($item->jawab_complex),
        //     'benar_salah' => $item->benar_salah,
        //     'setuju_tidak' => $item->setuju_tidak,
        //     'answered' => $item->answered,
        //     'soal' => [
        //         // 'audio' => $item->soal->audio,
        //         'banksoal_id' => $item->soal->banksoal_id,
        //         'direction' => $item->soal->direction,
        //         'id' => $item->soal->id,
        //         'jawabans' => $jawabans,
        //         'pertanyaan' => $item->soal->pertanyaan,
        //         'tipe_soal' => intval($item->soal->tipe_soal),
        //         'layout' => intval($item->soal->layout),
        //     ],
        //     'ragu_ragu' => $item->ragu_ragu,
        // ];
        var_dump($result);
        exit;
        return $result;

        // $soals_indexeds = $soals->keyBy('id');
        // $find = $find->map(function ($item) use ($soals_indexeds) {
        //     $item->soal = $soals_indexeds->get($item->soal_id);
        //     return $item;
        // });

        // var_dump($nextQuestion);
        // exit;
        return $nextQuestion;
    }

    // public function getNextQuestion($currentAbility, $jawabanPesertaId)
    // {
    //     // Implementasi perhitungan kemampuan berdasarkan model IRT 3PL
    //     // Ini melibatkan logika kompleks dan mungkin integrasi dengan perangkat lunak statistik

    //     // Contoh sederhana: pilih butir selanjutnya sesuai kemampuan peserta
    //     $nextQuestion = DB::table('banksoals')
    //         ->where('b', '>', $currentAbility)
    //         ->orderBy('b', 'asc')
    //         ->first();

    //     if (!$nextQuestion) {
    //         $nextQuestion = DB::table('banksoals')
    //             ->orderBy('b', 'asc')
    //             ->first();
    //     }

    //     if (!$nextQuestion) {
    //         return null;
    //     }

    //     return $nextQuestion;
    // }

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
