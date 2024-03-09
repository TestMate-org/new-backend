<?php

namespace App\Http\Controllers;

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

}
