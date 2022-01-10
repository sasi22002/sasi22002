<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Base;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Storage;

//use Illuminate\Support\Facades\Storage;

class FileController extends Controller
{
    public static function get($folder, $name)
    {
        $file = 'uploads/' . $folder . '/' . $name;

        if (Storage::disk('local')->exists($file)) {
            $type = Storage::mimeType($file);
            $file = Storage::get($file);
            // $timestamp = Storage::lastModified($file);

//           echo  $timestamp;
// die();
            return (new Response($file, 200))->header('Content-Type', $type);
        } else {
            return Base::touser('file not found', false, 404);
        }
    }

    public static function fileDelete($folder, $name)
    {
        $file = 'uploads/' . $folder . '/' . $name;

        if (Storage::disk('local')->exists($file)) {

            $type = Storage::delete($file);

            return Base::touser('File deleted', true);
        } else {
            return Base::touser('file not found', false);
        }
    }

    public static function fileDeletebulk(Request $request)
    {

        if ($request->input('data')) {
            $files = $request->input('data')['files'];
        }

        foreach ($files as $key => $value) {
            $dir  = dirname($value);
            $dirs = explode('/', $dir)[5];
            $file = 'uploads/' . $dirs . '/' . basename($value);

            if (Storage::disk('local')->exists($file)) {

                $type = Storage::delete($file);
            }

        }

        return Base::touser('File deleted', true);

    }

    public static function base2image($data, $type)
    {
        $data       = base64_decode($data); // base64 decoded image data
        $source_img = imagecreatefromstring($data);

        $filepath = uniqid() . $type;

        $file = storage_path() . '/app/uploads/' . Base::db_connection() . '/';

        if (!file_exists($file)) {
            mkdir($file, 0777, true);
        }
        $file = $file . $filepath;

        $imageSave = imagejpeg($source_img, $file);

        $file = Storage::get('uploads/'.Base::db_connection().'/'.$filepath);
        
        $t = Storage::disk('s3')->put('uploads' . '/' . Base::db_connection().'/'.$filepath,  $file, 'public');

        return Storage::disk('s3')->url('uploads' . '/' . Base::db_connection().'/'.$filepath);

    //    return 'https://delivery.manageteamz.com/api/uploads/' . Base::db_connection() . '/' . $filepath;
       // return 'http://' . Base::app_domain() . '/api/uploads/' . Base::db_connection() . '/' . $filepath;
    }

    public static function put(Request $request)
    {
        $data = $request->all();

        if (isset($request['api'])) {
            if (isset($data['files'])) {
                $files = $data['files'];

                $path = [];

                foreach ($files as $key => $file) {

                    //  $path[$key] = 'http://' . Base::app_domain() . '/api/uploads/' . self::base2image($file['file'], $file['type']);

                    $path[$key] = self::base2image($file['file'], $file['type']);

                }

                return Base::touser($path, true);
            } else {
                return Base::touser('No File Found');
            }
        } else {
            if ($request->hasFile('files')) {
                $path  = [];
                $files = $request->file('files');

                foreach ($files as $key => $file) {
                    if (isset($data['type']) && $data['type'] === 'img') {
                        if (substr($file->getMimeType(), 0, 5) !== 'image') {
                            return Base::touser('File not an Image');
                        }
                    }

                    $path[$key] = self::s3($file);
                }

                return Base::touser($path, true);
            } else {
                return Base::touser('No File Found');
            }
        }
    }
    
    public static function s3($file)
    {

        $t = Storage::disk('s3')->put('uploads' . '/' . Base::db_connection(), $file, 'public');

        return Storage::disk('s3')->url($t);

    }

    public static function store($file)
    {
        //$path = 'http://' . Base::app_domain() . '/api/' . $file->store('uploads/' . Base::db_connection());
        $path = 'https://delivery.manageteamz.com/api/' . $file->store('uploads/' . Base::db_connection());
        return $path;
    }

    public static function delete($id, $column, $model, $file)
    {
        $model = $model->find($id);

        $info = json_decode($model->$column, true);

        if (($key = array_search($file, $info)) !== false) {
            unset($info[$key]);

            $model          = $model->find($id);
            $model->$column = json_encode(array_values($info));
            $model->save();

            return 'ok';
        }

        return 'error';
    }
}
