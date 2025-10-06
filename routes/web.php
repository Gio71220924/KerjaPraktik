<?php

use App\Http\Controllers\UserController;
use App\Http\Controllers\AuthController;
use Illuminate\Routing\RouteGroup;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AtributController;
use App\Http\Controllers\AtributValueController;
use App\Http\Controllers\BCController;
use App\Http\Controllers\CaseController;
use App\Http\Controllers\CaseUserController;
use App\Http\Controllers\ConsultationController;
use App\Http\Controllers\DecisionTreeController;
use App\Http\Controllers\FCController;
use App\Http\Controllers\InferenceController;
use App\Http\Controllers\ProfileAdminController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\TreeController;


//USER
//project
Route::get('/project', function () {
    return view('admin.menu.case');
});
Route::get('/project/edit', [CaseController::class, 'edit'])->name('admin.menu.case.edit')->middleware('auth');
Route::put('/project/update', [CaseController::class, 'update'])->name('admin.menu.case.update')->middleware('auth');
//atribut
Route::get('/attributte', [AtributController::class, 'index'])->name('admin.menu.attributte')->middleware('auth');
Route::get('/attributte/create', [AtributController::class, 'create'])->name('admin.menu.atribut.tambah')->middleware('auth');
Route::post('/attributte', [AtributController::class, 'store'])->name('admin.menu.atribut.store')->middleware('auth');
Route::get('/attributte/{id}/edit', [AtributController::class, 'edit'])->name('admin.menu.atribut.edit')->middleware('auth');
Route::put('/attributte/{id}', [AtributController::class, 'update'])->name('admin.menu.atribut.update')->middleware('auth');
Route::delete('/attributte/{id}', [AtributController::class, 'destroy'])->name('admin.menu.atribut.hapus')->middleware('auth');
//atributValue
Route::get('/attributteValue', [AtributValueController::class, 'index'])->name('admin.menu.attributteValue')->middleware('auth');
Route::get('/attributteValue/create', [AtributValueController::class, 'create'])->name('admin.menu.atributValue.tambah')->middleware('auth');
Route::post('/attributteValue', [AtributValueController::class, 'store'])->name('admin.menu.atributValue.store')->middleware('auth');
Route::get('/attributteValue/{id}/edit', [AtributValueController::class, 'edit'])->name('admin.menu.atributValue.edit')->middleware('auth');
Route::put('/attributteValue/{id}', [AtributValueController::class, 'update'])->name('admin.menu.atributValue.update')->middleware('auth');
Route::delete('/attributteValue/{id}', [AtributValueController::class, 'destroy'])->name('admin.menu.atributValue.hapus')->middleware('auth');
//generate case
Route::get('/generateCase', [CaseUserController::class, 'showGenerateCaseForm'])->name('generate.case.form');
Route::post('/generateCase', [CaseUserController::class, 'generateCase'])->name('generate.case');
Route::post('/generateCase/store', [CaseUserController::class, 'store'])->name('generate.case.store');
Route::get('/generateCase/new', [CaseUserController::class, 'create'])->name('generate.case.create');
Route::get('/generateCase/{case_id}/edit', [CaseUserController::class, 'edit'])->name('generate.case.edit');
Route::put('/generateCase/{case_id}', [CaseUserController::class, 'update'])->name('generate.case.update');
Route::delete('/generateCase/{case_id}', [CaseUserController::class, 'destroy'])->name('generate.case.destroy');
//tree
Route::get('/tree', [DecisionTreeController::class, 'showTree'])->name('tree.show');
Route::get('/tree/generate', [DecisionTreeController::class, 'generateTree'])->name('tree.generate');
//rule
Route::get('/rule', function () {
    return view('admin.menu.rule');
});
Route::get('/rule/{user_id}/{case_num}', [RuleController::class, 'generateRule']);
//consultation
Route::get('/consultation', [ConsultationController::class, 'showConsultationForm'])->name('test.case.form');
Route::post('/consultation/store', [ConsultationController::class, 'store'])->name('test.case.store');
Route::get('/consultation/new', [ConsultationController::class, 'create'])->name('test.case.create');
Route::get('/consultation/{case_id}/edit', [ConsultationController::class, 'edit'])->name('test.case.edit');
Route::put('/consultation/{case_id}', [ConsultationController::class, 'update'])->name('test.case.update');
Route::delete('/consultation/{case_id}', [ConsultationController::class, 'destroy'])->name('test.case.destroy');
//inference
Route::get('/inference', function () {
    return view('admin.menu.inferensi');
});
Route::get('/inference/{user_id}/{case_num}', [InferenceController::class, 'generateInference']);
Route::post('/inference/{user_id}/{case_num}', [InferenceController::class, 'generate'])->name('inference.generate');
//fc
Route::get('/forwardChaining', function () {
    return view('admin.menu.fc');
});
Route::get('/forwardChaining/{user_id}/{case_num}', [FCController::class, 'generateFC']);
Route::post('/forwardChaining/{user_id}/{case_num}', [FCController::class, 'generateFC'])->name('inference.fc');
//bc
Route::get('/backward', function () {
    return view('admin.menu.bc');
});
Route::get('/backwardChaining/{user_id}/{case_num}', [BCController::class, 'generateBC']);
Route::post('/backwardChaining/{user_id}/{case_num}', [BCController::class, 'generateBC'])->name('inference.bc');
//detail
Route::get('/detail', function () {
    return view('admin.menu.detail');
});

//profile
Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
Route::post('/profile/update', [ProfileController::class, 'update'])->name('profile.update');
//logout
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');


//HALAMAN UTAMA
//login user biasa
Route::get('/', [AuthController::class, 'showLoginForm'])->name('login');
Route::post('/', [AuthController::class, 'login']);
//login
Route::get('/admin', [AuthController::class, 'showLoginAdminForm'])->name('login.admin');
Route::post('/admin', [AuthController::class, 'loginAdmin']);
//regis
Route::get('/registration', [AuthController::class, 'showRegistrationForm'])->name('registration');
Route::post('/registration', [AuthController::class, 'registration'])->name('registration');


//ADMIN
//user
Route::get('/user', [UserController::class, 'index'])->name('user.menu.user')->middleware('auth');
Route::put('/user/{id}/activate', [UserController::class, 'active'])->name('user.active');
Route::put('/user/{id}/inactivate', [UserController::class, 'inactive'])->name('user.inactive');
//profile
Route::get('/profile/admin', [ProfileAdminController::class, 'edit'])->name('profileAdmin.edit');
Route::post('/profile/admin/update', [ProfileAdminController::class, 'update'])->name('profileAdmin.update');
//

