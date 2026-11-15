<?php

use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookingController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\LeadController;
use App\Http\Controllers\PageController;
use Illuminate\Support\Facades\Route;

// Public pages
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/schedule', [PageController::class, 'schedule'])->name('schedule');
Route::get('/coaches', [PageController::class, 'coaches'])->name('coaches');
Route::get('/membership', [PageController::class, 'membership'])->name('membership');
Route::get('/contact', [PageController::class, 'contact'])->name('contact');
Route::post('/contact', [LeadController::class, 'storeInquiry'])->middleware('throttle:10,1')->name('contact.store');

// Athena AI website chat widget
Route::get('/chat', [ChatController::class, 'history'])->name('chat.history');
Route::post('/chat', [ChatController::class, 'send'])->name('chat.send');
Route::post('/chat/reset', [ChatController::class, 'reset'])->name('chat.reset');

// Auth
Route::middleware('guest')->group(function () {
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// Members
Route::middleware('auth')->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/dashboard/plan', [DashboardController::class, 'changePlan'])->name('dashboard.plan');
    Route::post('/dashboard/profile', [DashboardController::class, 'updateProfile'])->name('dashboard.profile');
    Route::post('/dashboard/password', [DashboardController::class, 'updatePassword'])->name('dashboard.password');
    Route::post('/bookings', [BookingController::class, 'store'])->name('bookings.store');
    Route::delete('/bookings/{booking}', [BookingController::class, 'destroy'])->name('bookings.destroy');
});

// Front desk (admin)
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminController::class, 'dashboard'])->name('dashboard');
    Route::get('/leads', [AdminController::class, 'leads'])->name('leads');
    Route::patch('/leads/{lead}', [AdminController::class, 'updateLead'])->name('leads.update');
    Route::get('/conversations', [AdminController::class, 'conversations'])->name('conversations');
    Route::get('/conversations/{conversation}', [AdminController::class, 'conversation'])->name('conversations.show');
    Route::get('/knowledge', [AdminController::class, 'knowledge'])->name('knowledge');
    Route::post('/knowledge/faqs', [AdminController::class, 'storeFaq'])->name('faqs.store');
    Route::put('/knowledge/faqs/{faq}', [AdminController::class, 'updateFaq'])->name('faqs.update');
    Route::delete('/knowledge/faqs/{faq}', [AdminController::class, 'destroyFaq'])->name('faqs.destroy');
    Route::put('/knowledge/plans/{plan}', [AdminController::class, 'updatePlan'])->name('plans.update');
    Route::put('/knowledge/settings', [AdminController::class, 'updateSettings'])->name('settings.update');
    Route::get('/members', [AdminController::class, 'members'])->name('members');
});
