<?php
use App\Controllers\AdminController;
use App\Controllers\AuthController;
use App\Controllers\ProfessorController;
use App\Controllers\StudentController;

return [
    'login' => [AuthController::class, 'login'],
    'logout' => [AuthController::class, 'logout'],
    'admin' => [AdminController::class, 'index'],
    'professor' => [ProfessorController::class, 'index'],
    'student' => [StudentController::class, 'index'],
];
