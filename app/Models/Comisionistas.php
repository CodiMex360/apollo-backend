<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class Comisionistas extends Model
{
    use HasFactory;
    protected $table = 'comisionistas';
    protected $primaryKey = 'id';

    protected $casts = [
        'comisiones_pactadas' => 'array'
    ];


    public static function validateBeforeSave($request) {
        $validateData = Validator::make($request, [
            'nombre' => 'required|string',
            'apellidos' => 'required|string',
            'tel_contacto' => 'required|string',
            'email_contacto' => 'required|email',
            'comisiones_pactadas' => 'required'
        ]);

        if ($validateData->fails()) {
            return $validateData->errors()->all();
        }

        return true;
    }
}
