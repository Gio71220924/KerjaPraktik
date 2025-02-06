<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Tree;
use App\Models\Kasus;
use App\Models\Atribut;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class TreeController extends Controller
{
    /**
     * Menampilkan halaman pohon keputusan.
     */
    public function index()
    {
        $user = Auth::user(); // Mendapatkan user yang sedang login
        $tree = new Tree();   // Membuat instance model Tree
        $tree->setTableForUser($user->user_id); // Mengatur tabel berdasarkan user ID

        // Periksa apakah tabel untuk user ini ada
        $tableExists = $tree->tableExists();

        // Jika tabel ada, ambil data rule; jika tidak, buat koleksi kosong
        $trees = $tableExists ? $tree->getRules() : collect();

        // Ambil data kasus berdasarkan user ID
        $kasus = Kasus::where('case_num', $user->user_id)->first();

        // Kirimkan data ke view
        return view('admin.menu.tree.index', compact('tableExists', 'trees', 'kasus'));
    }


    /**
     * Generate pohon keputusan berdasarkan algoritma C4.5.
     */
    public function generateTree($user_id)
    {
        $kasusTable = "case_user_{$user_id}";
        $atributTable = "atribut_user_{$user_id}";
        $treeTable = "tree_user_{$user_id}";

        // Ambil data kasus dan atribut
        $kasus = DB::table($kasusTable)->get();
        $atributs = DB::table($atributTable)->get();

        // Validasi data
        if ($kasus->isEmpty() || $atributs->isEmpty()) {
            return redirect()->back()->with('error', 'Data kasus atau atribut kosong. Tidak dapat membuat pohon.');
        }

        // Inisialisasi pohon
        $tree = new Tree();
        $tree->setTable($treeTable);

        // Hapus tree lama jika ada
        if ($tree->tableExists()) {
            DB::table($treeTable)->truncate();
        }

        // Jalankan algoritma C4.5
        $this->c45Algorithm($kasus, $atributs, $tree);

        return redirect()->route('tree.index')->with('success', 'Tree berhasil dibuat!');
    }

    /**
     * Implementasi Algoritma C4.5.
     */
    private function c45Algorithm($kasus, $atributs, $tree)
    {
        // Kalkulasi entropy awal
        $entropyAwal = $this->calculateEntropy($kasus);

        // Looping untuk membuat pohon
        $this->buildTree($kasus, $atributs, $tree, $entropyAwal);
    }

    /**
     * Fungsi untuk menghitung entropy.
     */
    private function calculateEntropy($data)
    {
        $total = count($data);
        $counts = [];

        // Hitung jumlah setiap kelas (goal)
        foreach ($data as $row) {
            $goal = $row->goal;
            if (!isset($counts[$goal])) {
                $counts[$goal] = 0;
            }
            $counts[$goal]++;
        }

        // Hitung entropy
        $entropy = 0;
        foreach ($counts as $count) {
            $p = $count / $total;
            $entropy -= $p * log($p, 2);
        }

        return $entropy;
    }

    /**
     * Fungsi rekursif untuk membangun pohon.
     */
    private function buildTree($data, $atributs, $tree, $entropyAwal, $parentNode = null)
    {
        if ($entropyAwal == 0 || $atributs->isEmpty()) {
            // Simpan node akhir (goal)
            $goal = $data->first()->goal;
            $tree->insertNode($parentNode, null, null, $goal);
            return;
        }

        // Hitung gain untuk setiap atribut
        $bestGain = 0;
        $bestAtribut = null;
        foreach ($atributs as $atribut) {
            $gain = $this->calculateGain($data, $atribut, $entropyAwal);

            if ($gain > $bestGain) {
                $bestGain = $gain;
                $bestAtribut = $atribut;
            }
        }

        if (!$bestAtribut) {
            return;
        }

        // Buat node pohon untuk atribut terbaik
        $tree->insertNode($parentNode, $bestAtribut->atribut_name, null, null);

        // Bagi data berdasarkan nilai atribut terbaik
        $values = $data->groupBy($bestAtribut->atribut_name);
        foreach ($values as $value => $subset) {
            $entropy = $this->calculateEntropy($subset);

            // Rekursi ke cabang berikutnya
            $this->buildTree($subset, $atributs->where('atribut_name', '!=', $bestAtribut->atribut_name), $tree, $entropy, $value);
        }
    }

    /**
     * Hitung gain untuk atribut.
     */
    private function calculateGain($data, $atribut, $entropyAwal)
    {
        $values = $data->groupBy($atribut->atribut_name);
        $total = count($data);

        $entropyTotal = 0;
        foreach ($values as $value => $subset) {
            $entropy = $this->calculateEntropy($subset);
            $entropyTotal += (count($subset) / $total) * $entropy;
        }

        return $entropyAwal - $entropyTotal;
    }
}
