<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Validator;

class AreasTrabajo extends Model
{
    use HasFactory;
    protected $table = 'areas_trabajo';
    protected $primaryKey = 'id';

    public static function validateBeforeSave($request) {
        $validateData = Validator::make($request, [
            'nombre' => 'required|string|max:100'
        ]);

        if ($validateData->fails()) {
            return $validateData->errors()->all();
        }

        return true;
    }
}