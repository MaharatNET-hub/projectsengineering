<?php

/*
| v2: company website + submission form + admin dashboard, all under /v2.
| v1 (the original demo at /) is untouched.
*/

use App\Http\Controllers\V2\AccountController;
use App\Http\Controllers\V2\Admin;
use App\Http\Controllers\V2\SiteController;
use App\Http\Controllers\V2\StudyController;
use App\Http\Controllers\V2\SubmitController;
use App\Http\Controllers\V2\TrackController;
use App\Http\Middleware\V2\AdminLocale;
use App\Http\Middleware\V2\EnsureInstalled;
use App\Http\Middleware\V2\ScopeSubmission;
use App\Http\Middleware\V2\SetLocale;
use Illuminate\Support\Facades\Route;

Route::prefix('v2')->name('v2.')->middleware([EnsureInstalled::class, SetLocale::class])->group(function () {
    Route::get('/', [SiteController::class, 'home'])->name('home');
    Route::get('about', [SiteController::class, 'about'])->name('about');
    Route::get('services', [SiteController::class, 'services'])->name('services');
    Route::get('projects', [SiteController::class, 'projects'])->name('projects');
    Route::get('projects/{project}', [SiteController::class, 'project'])->name('project');
    Route::get('contact', [SiteController::class, 'contact'])->name('contact');
    Route::post('contact', [SiteController::class, 'contactSend'])->middleware('throttle:v2-contact')->name('contact.send');
    Route::get('media/{path}', [SiteController::class, 'media'])->where('path', '.*')->name('media');

    Route::get('submit', [SubmitController::class, 'form'])->name('submit');
    Route::post('submit', [SubmitController::class, 'create'])->middleware('throttle:v2-submit')->name('submit.create');
    Route::post('submit/{code}/chunk', [SubmitController::class, 'chunk'])->name('submit.chunk');
    Route::post('submit/{code}/step', [SubmitController::class, 'step'])->name('submit.step');
    Route::get('submit/{code}/thanks', [SubmitController::class, 'thanks'])->name('submit.thanks');

    // study review requests: a form per study type + a supporting file
    Route::get('studies', [StudyController::class, 'index'])->name('studies');
    Route::post('studies/upload', [StudyController::class, 'uploadStart'])->middleware('throttle:v2-study')->name('studies.upload');
    Route::post('studies/upload/{token}/chunk', [StudyController::class, 'uploadChunk'])->name('studies.chunk');
    Route::post('studies/upload/{token}/extract', [StudyController::class, 'uploadExtract'])->name('studies.extract');
    Route::get('studies/r/{code}', [StudyController::class, 'show'])->middleware('throttle:v2-track')->name('studies.show');
    Route::get('studies/r/{code}/report', [StudyController::class, 'report'])->middleware('throttle:v2-track')->name('studies.report');
    Route::get('studies/{type}/template/{section}', [StudyController::class, 'template'])->name('studies.template');
    Route::post('studies/{type}/import/{section}', [StudyController::class, 'import'])->middleware('throttle:v2-study')->name('studies.import');
    Route::get('studies/{type}', [StudyController::class, 'form'])->name('studies.form');
    Route::post('studies/{type}', [StudyController::class, 'create'])->middleware('throttle:v2-study')->name('studies.create');

    // client accounts
    Route::get('account/login', [AccountController::class, 'loginForm'])->middleware('guest:client')->name('account.login');
    Route::post('account/login', [AccountController::class, 'login'])->middleware(['guest:client', 'throttle:v2-login'])->name('account.login.post');
    Route::get('account/register', [AccountController::class, 'registerForm'])->middleware('guest:client')->name('account.register');
    Route::post('account/register', [AccountController::class, 'register'])->middleware(['guest:client', 'throttle:v2-contact'])->name('account.register.post');
    Route::middleware('auth:client')->group(function () {
        Route::get('account', [AccountController::class, 'index'])->name('account');
        Route::post('account/logout', [AccountController::class, 'logout'])->name('account.logout');
        Route::post('account/claim', [AccountController::class, 'claim'])->middleware('throttle:v2-track')->name('account.claim');
        Route::put('account/profile', [AccountController::class, 'profile'])->name('account.profile');
    });

    Route::get('track', [TrackController::class, 'form'])->middleware('throttle:v2-track')->name('track');
    Route::get('track/{code}', [TrackController::class, 'show'])->middleware('throttle:v2-track')->name('track.show');
    Route::get('track/{code}/download', [TrackController::class, 'download'])->middleware('throttle:v2-track')->name('track.download');

    // ---- admin
    Route::prefix('admin')->name('admin.')->middleware(AdminLocale::class)->group(function () {
        Route::get('login', [Admin\AuthController::class, 'form'])->name('login');
        Route::post('login', [Admin\AuthController::class, 'login'])->middleware('throttle:v2-login')->name('login.post');

        Route::middleware(['auth', \App\Http\Middleware\V2\ActiveUser::class])->group(function () {
            Route::post('logout', [Admin\AuthController::class, 'logout'])->name('logout');
            Route::get('/', [Admin\DashboardController::class, 'index'])->name('dashboard');

            Route::get('submissions', [Admin\SubmissionController::class, 'index'])->name('submissions');
            Route::get('submissions/export', [Admin\SubmissionController::class, 'export'])->name('submissions.export');
            Route::get('submissions/{submission}', [Admin\SubmissionController::class, 'show'])->name('submissions.show');
            Route::patch('submissions/{submission}', [Admin\SubmissionController::class, 'update'])->name('submissions.update');
            Route::delete('submissions/{submission}', [Admin\SubmissionController::class, 'destroy'])->name('submissions.destroy');
            Route::get('submissions/{submission}/original', [Admin\SubmissionController::class, 'original'])->name('submissions.original');
            Route::get('submissions/{submission}/issued', [Admin\SubmissionController::class, 'issued'])->name('submissions.issued');
            Route::post('submissions/{submission}/email', [Admin\SubmissionController::class, 'email'])->name('submissions.email');
            Route::get('submissions/{submission}/workspace', [Admin\SubmissionController::class, 'workspace'])->name('submissions.workspace');

            // the analysis tool (same engine and screens as v1), scoped to one submission's folder
            Route::prefix('submissions/{submission}')->middleware(ScopeSubmission::class)->name('ws.')->group(function () {
                Route::get('api/state', [Admin\WorkspaceController::class, 'state'])->name('state');
                Route::post('api/start', [Admin\WorkspaceController::class, 'start'])->name('start');
                Route::post('api/step', [Admin\WorkspaceController::class, 'step'])->name('step');
                Route::post('api/review', [Admin\WorkspaceController::class, 'review'])->name('review');
                Route::post('api/settings', [Admin\WorkspaceController::class, 'settings'])->name('settings');
                Route::post('api/rules', [Admin\WorkspaceController::class, 'rules'])->name('rules');
                Route::post('api/generate', [Admin\WorkspaceController::class, 'generate'])->name('generate');
                Route::get('out/{name}', [Admin\WorkspaceController::class, 'output'])->where('name', '[A-Za-z0-9._-]+')->name('output');
            });

            Route::get('studies', [Admin\StudyController::class, 'index'])->name('studies');
            Route::get('studies/stats', [Admin\StudyStatsController::class, 'index'])->name('studies.stats');
            Route::get('studies/{study}', [Admin\StudyController::class, 'show'])->name('studies.show');
            Route::patch('studies/{study}', [Admin\StudyController::class, 'update'])->name('studies.update');
            Route::post('studies/{study}/assign', [Admin\StudyController::class, 'assign'])->name('studies.assign');
            Route::post('studies/{study}/reanalyse', [Admin\StudyController::class, 'reanalyse'])->name('studies.reanalyse');
            Route::get('studies/{study}/file', [Admin\StudyController::class, 'file'])->name('studies.file');
            Route::get('studies/{study}/report', [Admin\StudyController::class, 'report'])->name('studies.report');
            Route::post('studies/{study}/email', [Admin\StudyController::class, 'email'])->name('studies.email');
            Route::get('me', [Admin\SettingsController::class, 'me'])->name('me');

            // admins only: website content, settings, users, clients, study types, deleting studies
            Route::middleware('v2.admin')->group(function () {
                Route::delete('studies/{study}', [Admin\StudyController::class, 'destroy'])->name('studies.destroy');

                Route::get('study-types', [Admin\StudyTypeController::class, 'index'])->name('study-types');
                Route::post('study-types', [Admin\StudyTypeController::class, 'store'])->name('study-types.store');
                Route::get('study-types/{type}', [Admin\StudyTypeController::class, 'edit'])->name('study-types.edit');
                Route::put('study-types/{type}', [Admin\StudyTypeController::class, 'update'])->name('study-types.update');
                Route::delete('study-types/{type}', [Admin\StudyTypeController::class, 'reset'])->name('study-types.reset');

                Route::get('users', [Admin\UserController::class, 'index'])->name('users');
                Route::post('users', [Admin\UserController::class, 'store'])->name('users.store');
                Route::patch('users/{user}', [Admin\UserController::class, 'update'])->name('users.update');

                Route::get('clients', [Admin\ClientController::class, 'index'])->name('clients');

                Route::get('messages', [Admin\MessageController::class, 'index'])->name('messages');
                Route::get('messages/{message}', [Admin\MessageController::class, 'show'])->name('messages.show');
                Route::delete('messages/{message}', [Admin\MessageController::class, 'destroy'])->name('messages.destroy');

                Route::resource('projects', Admin\ProjectController::class)->except('show');
                Route::resource('services', Admin\ServiceController::class)->except('show');

                Route::get('company', [Admin\SettingsController::class, 'company'])->name('company');
                Route::put('company', [Admin\SettingsController::class, 'saveCompany'])->name('company.save');
                Route::get('settings', [Admin\SettingsController::class, 'settings'])->name('settings');
                Route::put('settings', [Admin\SettingsController::class, 'saveSettings'])->name('settings.save');
                Route::post('settings/test-mail', [Admin\SettingsController::class, 'testMail'])->name('settings.test-mail');
            });
            Route::put('account', [Admin\SettingsController::class, 'password'])->name('account.password');
        });
    });
});
