<?php

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

const forbidden_words = ['update', 'edit', 'create', 'show', 'store', 'destroy'];

function generateSlug($class, $fields) {
    $fields['slug'] = isset($fields['slug']) ? Str::slug($fields['slug']) : (isset($fields['name']) ? Str::slug($fields['name']) : Str::slug($fields['title']));
    if (!$fields['slug']) $fields['slug'] = 'slug';
    $fields['slug'] = substr($fields['slug'], 0, 50);
    $slug = is_numeric($fields['slug']) ? 'c-' . $fields['slug'] : $fields['slug'];
    $i = 2;
    while (in_array($slug, forbidden_words) || (isset($fields['comic_id']) ? $class::slug($fields['comic_id'], $slug) : $class::slug($slug))) {
        $slug = $fields['slug'] . '-' . $i;
        $i++;
    }
    return $slug;
}

function getFormFieldsForValidation($fields): array {
    $form_fields = [];
    foreach ($fields as $field) {
        $values = $field['values'];
        if (isset($field['parameters']['required']) && $field['parameters']['required']) {
            $values[] = 'required';
        } else {
            $values[] = 'nullable';
        }
        if (isset($field['parameters']['prohibited']) && $field['parameters']['prohibited']) {
            $values[] = 'prohibited';
        }
        $form_fields[$field['parameters']['field']] = $values;
    }
    return $form_fields;
}

function getFieldsToUnsetIfNull($fields): array {
    $array_fields = [];
    foreach ($fields as $field) {
        if(isset($field['parameters']['edit']) && $field['parameters']['edit']) $array_fields[] = $field['parameters']['field'];
    }
    return $array_fields;
}

function getFieldsFromRequest($request, $form_fields, $to_unset_if_null=[]): array {
    $fields = [];
    foreach ($form_fields as $key => $value) {
        if ($request->$key != null) $fields[$key] = $request->$key;
        elseif (!$request->has($key) && in_array($key, $to_unset_if_null)) unset($fields[$key]);
        else $fields[$key] = null;
    }
    return $fields;
}

function trimChar($string, $char): string {
    $c = $char[0];
    $string = preg_replace('/\s*' . $c . '\s*/', $c, $string);
    $string = preg_replace('/' . $c . '+' . $c . '+/', $c, $string);
    return trim($string, ' ' . $c);
}

function createZip($zip_absolute_path, $files) {
    if (empty($files)) return;
    $zip = new ZipArchive();
    $res = $zip->open($zip_absolute_path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    if (!$res) {
        Log::error("createZip", ['download' => $zip_absolute_path, 'files' => $files]);
        Log::error("ZipArchive::open failed with code", $res);
    }
    foreach ($files as $file) {
        $zip->addFile($file['source'], $file['dest']);
    }
    try {
        $zip->close();
    } catch (Exception $exception) {
        Log::error("createZip", ['download' => $zip_absolute_path, 'files' => $files]);
        Log::error($exception);
    }
}

function createPdf($pdf_absolute_path, $files) {
    // Sadly Intervention Image library doesn't support pdf...
    if (empty($files)) return;
    try {
        $pdf = new Imagick($files);
        $pdf->setImageFormat("pdf");
        $pdf->writeImages($pdf_absolute_path, true);
    } catch (ImagickException $exception) {
        Log::error("createPdf", ['download' => $pdf_absolute_path, 'files' => $files]);
        Log::error($exception);
    }
}

function cleanDirectoryByExtension($path, $ext, $exclusions=[], $dry_run=false): array {
    $files = Storage::files($path);
    foreach ($files as $key => $value) {
        if (!preg_match("/.$ext$/", $value) || in_array($value, $exclusions)) unset($files[$key]);
    }
    if (!$dry_run) Storage::delete($files);
    return $files;
}

function convertToTimezone($datetime, $timezone) {
    return Carbon::createFromFormat('Y-m-d H:i:s', $datetime, 'UTC')->tz($timezone);
}

function convertToUTC($datetime, $timezone){
    return Carbon::createFromFormat('Y-m-d\TH:i', $datetime, $timezone)->tz('UTC');
}

function getNameFromId($array, $id): string {
    $res = array_filter($array, function ($e) use (&$id) {return $e['id'] == $id;});
    if(empty($res)) return "N/A";
    else return array_pop($res)["name"];
}

function getSmallThumbnail($thumbnail) {
    if (!$thumbnail) return null;
    return preg_replace('/(\.[^.?]+)(\?.*)?$/', '-small.jpg$2', $thumbnail);
}

function strip_forbidden_chars($string) {
    // Remove "%", "#" and "?" from filename because apache mod_rewrite decodes it in the internal redirect
    // For example a file called "test%3dfile.jpg" will be urlencoded to "test%253dfile.jpg"
    // The browser will send a request for "test%253dfile.jpg" which will be decoded to "test%3dfile.jpg"
    // Apache will check correctly for "test%3dfile.jpg" in the filesystem
    // If the user can't use the /public path as root directory the default .htaccess file will
    // perform a internal redirect with mod_rewrite if the file is not found (which in this case always happens)
    // The internal redirect will decode the filename again in "test=file.jpg" and the file will not be found (I think this is a bug)
    // Furthermore if the user has a https redirect and is not using NE (noescape) flag in the rewrite rule other problems could arise
    // Just for being sure we remove these characters from the filename
    return preg_replace("/%|#|\?/", "", $string);
}
