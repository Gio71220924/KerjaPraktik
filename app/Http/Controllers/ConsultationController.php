<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class ConsultationController extends Controller
{
    public function showConsultationForm()
    {
        $user = Auth::user();
        $generate = new \App\Models\Consultation();
        $generate->setTableForUser($user->user_id);

        // Periksa apakah tabel ada
        $tableExists = $generate->tableExists();
        $generateCase = $tableExists ? $generate->getRules() 
        : collect();

        // Mengambil nama kolom tabel yang sudah di-generate
        $columns = $tableExists ? Schema::getColumnListing($generate->getTable()) : [];

        $kasus = \App\Models\Kasus::where('case_num', $user->user_id)->paginate(25);
        
        return view('admin.menu.testCase', compact('generateCase', 'columns', 'tableExists'));
    }

    public function create()
    {
        $user_id = Auth::user()->user_id;
        $atributs = DB::table('atribut')
                ->where('user_id', $user_id)
                ->orderBy('goal', 'desc')
                ->where('goal', 'F') 
                ->get();
        return view('admin.menu.testCaseTambah', compact('atributs'));
    }

    public function store(Request $request)
    {
        // Mendapatkan user_id dari user yang sedang login
        $user_id = Auth::id();
        $tableName = 'test_case_user_' . $user_id;  // Menentukan nama tabel sesuai dengan user_id
    
        // Mendapatkan daftar atribut untuk user_id yang sedang login
        $atributs = DB::table('atribut')
                        ->where('user_id', $user_id)
                        ->where('goal', 'F')
                        ->orderBy('goal', 'desc')
                        ->get();
    
        // Menyiapkan array kosong untuk menyimpan data
        $data = [
            'user_id' => $user_id,
            'case_num' => $user_id,  // Mengambil nilai case_num
        ];
    
        $queryCheck = DB::table($tableName)->where('user_id', $user_id);
    
        // Loop untuk memeriksa kolom dan menyusun data berdasarkan input form
        foreach ($atributs as $atribut) {
            $atribut_name = $atribut->atribut_name;
            $atribut_id = $atribut->atribut_id;
    
            // Membangun nama kolom berdasarkan atribut_name
            $kolom_name = "{$atribut_id}_{$atribut_name}";
    
            // Mengambil nilai input untuk kolom ini
            if ($request->has($kolom_name)) {
                $input_value = $request->input($kolom_name);
    
                // Mengambil nilai enum yang valid untuk atribut ini
                $valid_values = DB::table('atribut_value')
                                  ->where('user_id', $user_id)
                                  ->where('atribut_id', $atribut_id)
                                  ->where(DB::raw("CONCAT(value_id, '_', value_name)"), $input_value)
                                  ->exists();
    
                if ($valid_values) {
                    $data[$kolom_name] = $input_value;
                    $queryCheck->where($kolom_name, $input_value);
                } else {
                    return redirect()->back()->withErrors("Invalid value for attribute {$atribut_name}.");
                }
            }
            
        }
    
        // Cek apakah data sudah ada
        if ($queryCheck->exists()) {
            return redirect()->back()->withErrors('Data already exists.');
        }

        // Setelah data disiapkan, lakukan insert ke dalam tabel
        if (!empty($data)) {
            DB::table($tableName)->insert($data);
            return redirect()->route('inference.generate', ['user_id' => $user_id, 'case_num' => $user_id])->with('success', 'Case added successfully.');
        } else {
            return redirect()->back()->withErrors('No data to insert.');
        }
    }

    public function edit($case_id)
    {
        $user_id = Auth::id();
        $tableName = 'test_case_user_' . $user_id;
    
        // Cari data case berdasarkan case_id
        $case = DB::table($tableName)->where('case_id', $case_id)->first();
    
        if (!$case) {
            return redirect()->route('test.case.form')->withErrors('Case not found.');
        }
    
        // Ambil daftar atribut untuk user
        $atributs = DB::table('atribut')
                    ->where('user_id', $user_id)
                    ->orderBy('goal', 'desc')
                    ->where('goal', 'F') 
                    ->get();
    
        return view('admin.menu.testCaseEdit', compact('case', 'atributs', 'tableName'));
    }

    public function update(Request $request, $case_id)
    {
        $user_id = Auth::id();
        $tableName = 'test_case_user_' . $user_id;

        // Validasi data
        $atributs = DB::table('atribut')
                    ->where('user_id', $user_id)
                    ->orderBy('goal', 'desc')
                    ->get();

        $data = [];

        $queryCheck = DB::table($tableName)->where('user_id', $user_id)->where('case_id', '!=', $case_id);

        foreach ($atributs as $atribut) {
            $kolom_name = "{$atribut->atribut_id}_{$atribut->atribut_name}";

            if ($request->has($kolom_name)) {
                $input_value = $request->input($kolom_name);
    
                // Mengambil nilai enum yang valid untuk atribut ini
                $valid_values = DB::table('atribut_value')
                                  ->where('user_id', $user_id)
                                  ->where('atribut_id', $atribut->atribut_id)
                                  ->where(DB::raw("CONCAT(value_id, '_', value_name)"), $input_value)
                                  ->exists();
    
                if ($valid_values) {
                    $data[$kolom_name] = $input_value;
                    // Tambahkan kondisi pengecekan ke query
                    $queryCheck->where($kolom_name, $input_value);
                } else {
                    return redirect()->back()->withErrors("Invalid value for attribute {$atribut->atribut_name}.");
                }
            }
        }

        // Cek apakah data dengan kombinasi yang sama sudah ada (kecuali yang sedang diedit)
        if ($queryCheck->exists()) {
            return redirect()->back()->withErrors('Data already exists.');
        }
        
        // Update data berdasarkan case_id
        DB::table($tableName)->where('case_id', $case_id)->update($data);

        return redirect()->route('test.case.form')->with('success', 'Case updated successfully.');
    }
    public function destroy($case_id)
    {
        $user_id = Auth::id();
        $tableName = 'test_case_user_' . $user_id;
    
        // Hapus data berdasarkan case_id
        DB::table($tableName)->where('case_id', $case_id)->delete();
    
        return redirect()->route('test.case.form')->with('success', 'Case deleted successfully.');
    }
  

}
