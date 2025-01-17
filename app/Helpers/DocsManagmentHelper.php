<?php

namespace App\Helpers;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class DocsTypeEnum
{
    const LICENCE = 'licencia_conducir';
    const INE = 'ine';
}

class DocsValidParams
{
    const VALIDMODELS = ['clientes', 'contratos', 'cobranza', 'check_list','categorias_vehiculos'];
    //const VALIDMODELIDS = ['cliente_id', 'contrato_id', 'cobranza_id'];
    const validDocTypes = ['licencia_conducir', 'ine', 'cupon', 'voucher', 'check_indicator','layout'];
}

class DocsStatusEnum
{
    const ACTIVO = 1;
    const INACTIVO = 0;
    const BORRADO = -1;
}

class DocsManagmentHelper
{
    public static function save(Request $request) {

        $getFiles = $request->allFiles();
        if (!$getFiles) {
            return (object) ['ok' => false, 'errors' => ['Debe existir un archivo para procesar']];
        }
        if ($getFiles && count($getFiles) === 0) {
            return (object) ['ok' => false, 'errors' => ['Debe existir un archivo para procesar']];
        }

        $validate = self::validateFiles($request);

        if ($validate->ok === false) {
            return $validate;
        }

        return self::storeFiles($request);
    }

    public static function getAllDocs(Request $request) {

        $validate = self::validateBeforeGet($request);

        if ($validate->ok === false) {
            return $validate;
        }
        $response = [];
        //dd($validate);
        if ($request->has('id') && $request->id > 0 && $validate->data->id) {
            $dirFile = $request->model_id.'/'.$request->doc_type.'/'.$validate->data->nombre_archivo;

            if (Storage::disk($request->model)->exists($dirFile) === false) {
                return (object) ['ok' => false, 'errors' => ['El archivo ya no esta disponible']];
            }

            $fileData = Storage::disk($request->model)->get($dirFile);
            $encodedFile = base64_encode($fileData);
            $mimeType = Storage::disk($request->model)->mimeType($dirFile);

            array_push($response, [
                'mime_type' => $mimeType,
                'file' => $encodedFile
            ]);

            return (object) ['ok' => true, 'data' => $response];

        } else {
            $dir = $request->model_id.'/'.$request->doc_type;
        }

        if ($request->has('in_directory') && $request->in_directory === true) {
            $files = Storage::disk($request->model)->files($dir);
        } else {
            $files = $validate->data;
        }

        if (!$files) {
            return (object) ['ok' => false, 'errors' => ['No hay información para mostrar']];
        }
        $base64Prefix = 'data:image/jpeg;base64,';
        if ($files && count($files) > 0) {

            for ($i = 0; $i < count($files); $i++) {
                if ($request->has('in_directory') && $request->in_directory === true) {
                    $fileData = Storage::disk($request->model)->get($files[$i]);
                    $encodedFile = base64_encode($fileData);
                    $mimeType = Storage::disk($request->model)->mimeType($files[$i]);

                    array_push($response, [
                        'etiqueta' => null,
                        'position' => $i,
                        'success' => true,
                        'file_id' => null,
                        'doc_type' => $request->doc_type,
                        'model' => $request->model,
                        'model_id' => $request->model_id,
                        //'model_id_value' => $request->model_id_value,
                        'mime_type' => $mimeType,
                        'file' => 'data:'.$mimeType.';base64,'.$encodedFile
                    ]);
                } else {
                    $dirFile = $request->model_id.'/'.$request->doc_type.'/'.$files[$i]->nombre_archivo;
                    if (Storage::disk($request->model)->exists($dirFile) === false) {
                        continue;
                    }
                    $fileData = Storage::disk($request->model)->get($dirFile);
                    $encodedFile = base64_encode($fileData);
                    $mimeType = Storage::disk($request->model)->mimeType($dirFile);

                    array_push($response, [
                        'nombre_archivo' => $files[$i]->nombre_archivo,
                        'etiqueta' => $files[$i]->etiqueta,
                        'position' => $files[$i]->posicion,
                        'success' => true,
                        'file_id' => $files[$i]->id,
                        'doc_type' => $request->doc_type,
                        'model' => $request->model,
                        'model_id' => $request->model_id,
                        //'model_id_value' => $request->model_id_value,
                        'mime_type' => $mimeType,
                        'file' => 'data:'.$mimeType.';base64,'.$encodedFile
                    ]);
                }

            }
        }
        return (object) ['ok' => true, 'total' => count($response), 'data' => $response];
    }

    public static function deleteFile(Request $request) {
        if ($request->has('id') === false) {
            return (object) ['ok' => false, 'errors' => ['Debe indicar el id del archivo a elimiar']];
        }
        $validate = self::validateBeforeGet($request);

        if ($validate->ok === false) {
            return $validate;
        }

        $dirFile = $request->model_id.'/'.$request->doc_type.'/'.$validate->data->nombre_archivo;

        if (Storage::disk($request->model)->exists($dirFile) === false) {
            return (object) ['ok' => false, 'errors' => ['El archivo ya no esta disponible']];
        }

        $fileDataDelete = Storage::disk($request->model)->delete($dirFile);

        if ($fileDataDelete === false) {
            return (object) ['ok' => false, 'errors' => ['Se presento un error al elimiar el archivo']];
        }

        $delete = DB::table('modelos_docs')->where('id', '=', $request->id)->update([
            'borrado' => true,
            'fecha_borrado' => Carbon::now(),
            'estatus' => DocsStatusEnum::BORRADO,
            'updated_at' => Carbon::now()
        ]);
        if (!$delete) {
            return (object) ['ok' => false, 'errors' => ['Se presento un error al guardar la eliminación del archivo']];
        }

        return (object) ['ok' => true, 'message' => 'Archivo eliminado correctamente'];
    }

    public function replaceFile(Request $request) {
        $oldFile = null;
        $id = null;

        if ($request->has('id')) {
            $data = DB::table('modelos_docs')->where('id', '=', $request->id)->first();
            $id = $request->id;

            if ($data) {
                $oldFile = $data->nombre_archivo;
            }
        }

        if (isset($oldFile)) {
            Storage::disk($request->model)->delete($dir.'/'.$oldFile);
        }
    }


    //#region PRIVATE FUNCTIONS

    private static function validateFiles(Request $request) {
        $validateData = Validator::make($request->all(), [
            'doc_type' => 'required|string',
            'model' => 'required|string',
            'model_id' => 'required|numeric',
            //'model_id_value' => 'required|numeric',
            'files.*' => 'required|mimes:png,jpg,jpeg,pdf|max:4096',
            'positions' => 'required|json',
            'etiquetas' => 'required|json'
        ]);
        //dd(json_decode($request->position));
        if ($validateData->fails()) {
            //dd($validateData->errors());
            return (object) ['ok' => false, 'errors' => $validateData->errors()->all()];
        }

        $validModels = DocsValidParams::VALIDMODELS;
        //$validModelIds = DocsValidParams::VALIDMODELIDS;
        $validDocTypes = DocsValidParams::validDocTypes;

        if (in_array($request->model, $validModels) === false) {
            return (object) ['ok' => false, 'errors' => ['El modelo:'. $request->model. ' es invalido']];
        }

        // if (in_array($request->model_id, $validModelIds) === false) {
        //     return (object) ['ok' => false, 'errors' => ['El modelo id: '. $request->model_id. ' es invalido']];
        // }

        if (in_array($request->doc_type, $validDocTypes) === false) {
            return (object) ['ok' => false, 'errors' => ['El tipo de documento es invalido', ['Tipos válidos' => $validDocTypes]]];
        }

        return (object) ['ok' => true];

    }

    private static function validateBeforeGet(Request $request) {
        $validateData = Validator::make($request->all(), [
            'doc_type' => 'required|string',
            'model' => 'required|string',
            'model_id' => 'required|numeric',
            //'model_id_value' => 'required|numeric',
            'id' => 'nullable|numeric'
        ]);

        if ($validateData->fails()) {
            return (object) ['ok' => false, 'errors' => $validateData->errors()->all()];
        }

        $validModels = DocsValidParams::VALIDMODELS;
        //$validModelIds = DocsValidParams::VALIDMODELIDS;
        $validDocTypes = DocsValidParams::validDocTypes;

        if (in_array($request->model, $validModels) === false) {
            return (object) ['ok' => false, 'errors' => ['El modelo:'. $request->model. ' es invalido']];
        }

        // if (in_array($request->model_id, $validModelIds) === false) {
        //     return (object) ['ok' => false, 'errors' => ['El modelo id: '. $request->model_id. ' es invalido']];
        // }

        if (in_array($request->doc_type, $validDocTypes) === false) {
            return (object) ['ok' => false, 'errors' => ['El tipo de documento es invalido', ['Tipos válidos' => $validDocTypes]]];
        }

        $query =  DB::table('modelos_docs')
            ->where('modelo', $request->model)
            ->where('modelo_id', '=', $request->model_id)
            ->where('estatus', '=', DocsStatusEnum::ACTIVO)
            ->orderBy('posicion', 'ASC');

        if ($request->has('id') && $request->id > 0) {
            $data = $query->where('id', '=', $request->id)->first();

            if (!$data) {
                return (object) ['ok' => false, 'errors' => ['No se encontro información para mostrar']];
            }
            return (object) ['ok' => true, 'data' => $data];
        }

        $validInDB = $query->get();

        if ($validInDB && count($validInDB) === 0) {
            return (object) ['ok' => false, 'errors' => ['No existe información para mostrar']];
        } else if (!$validInDB) {
            return (object) ['ok' => false, 'errors' => ['No existe información para mostrar']];
        }
        //dd($validInDB);

        return (object) ['ok' => true, 'data' => $validInDB];
    }

    private static function storeFiles(Request $request) {
        $dir = null;
        $savedStorage = null;
        $fileName = null;

        $errorsDisk = 0;
        $errorsDB = 0;
        $_response = [];

        //dd(($request->file('files')));

        $positionsImg = json_decode($request->positions);
        $etiquetas = json_decode($request->etiquetas);

        // Guardamos archivo
        for ($i = 0; $i < count($request->file('files')); $i++) {

            $dir = $request->model_id.'/'.$request->doc_type;
            $rand = rand(2, 100);

            $validImageMimeTypes = ['image/png','image/jpg','image/jpeg'];
            $mimeType = 'image/png';

            if (in_array($request->file('files')[$i]->getClientMimeType(), $validImageMimeTypes)) {
                $fileName = Carbon::now()->unix().$rand.'.png';
                $img = \Image::make($request->file('files')[$i])->resize(900, null, function ($constraint) { $constraint->aspectRatio(); } );
                $savedStorage = Storage::disk($request->model)->put($dir.'/'.$fileName, (string) $img->encode('png'));
            } else {
                $mimeType = $request->file('files')[$i]->getClientMimeType();
                $fileName = Carbon::now()->unix().$rand.'.'.$request->file('files')[$i]->getClientOriginalExtension();
                $savedStorage = Storage::disk($request->model)->putFileAs($dir, $request->file('files')[$i], $fileName);
            }

            if ($savedStorage === false ) {
                $errorsDisk ++;
            }

            DB::beginTransaction();
            $payload = [
                'tipo_doc' => $request->doc_type,
                'nombre_archivo' => $fileName,
                'estatus' => DocsStatusEnuM::ACTIVO,
                'modelo' => $request->model,
                'modelo_id' => $request->model_id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now(),
                'posicion' => $positionsImg[$i],
                'tipo_archivo' => $mimeType,
                'etiqueta' => $etiquetas[$i]
            ];
            $savedId = DB::table('modelos_docs')->insertGetId(
                $payload
            );

            if ($savedId > 0) {
                DB::commit();
                array_push($_response, [
                    'position' => $positionsImg[$i],
                    'etiqueta' => $etiquetas[$i],
                    'success' => true,
                    'file_id' => $savedId,
                    'doc_type' => $request->doc_type,
                    'modelo' => $request->model,
                    'modelo_id' => $request->model_id,
                    //'model_id_value' => $request->model_id_value,
                    'mime_type' => $mimeType
                ]);
            } else {
                DB::rollBack();
                $errorsDB ++;
                array_push($_response, [
                    'position' => $positionsImg[$i],
                    'etiqueta' => $etiquetas[$i],
                    'success' => false,
                    'file_id' => $savedId,
                    'doc_type' => $request->doc_type,
                    'modelo' => $request->model,
                    'modelo_id' => $request->model_id,
                    //'model_id_value' => $request->model_id_value,
                    'mime_type' => $mimeType
                ]);
            }
        }

        if ($errorsDisk > 0) {
            return (object) ['ok' => true, 'errors' => ['Algo salio mal al guardar alguno de los archivos en disco.', 'Ocurrieron '. $errorsDisk.' errores'], 'payload' => $_response];
        } else if ($errorsDB > 0) {
            return (object) ['ok' => true, 'errors' =>  ['Algo salio mal al guardar alguno de los archivos en la base de datos.', 'Ocurrieron '. $errorsDB.' errores'], 'payload' => $_response];
        }

        return (object) ['ok' => true, 'message' => 'Archivos almacenados correctamente', 'payload' => $_response];

    }

    //#endregion
}
