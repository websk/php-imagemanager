<?php

namespace WebSK\Image;

use WebSK\Config\ConfWrapper;
use WebSK\Utils\Exits;

/**
 * Class ImageController
 * @package WebSK\Image\Image
 */
class ImageController
{

    public function uploadAction(): void
    {
        echo self::processUploadImage();
    }

    public static function processUploadImage()
    {
        $root_images_folder = ImageConstants::IMG_ROOT_FOLDER;

        $json_arr = [];

        if (array_key_exists('name', $_FILES['upload_image']) && is_array($_FILES['upload_image']['name'])) {
            $files_arr = self::rebuildFilesArray($_FILES['upload_image']);
        } else {
            $files_arr[] = $_FILES['upload_image'];
        }

        $target_folder = '';
        if (array_key_exists('target_folder', $_POST)) {
            $target_folder = $_POST['target_folder'];
        }

        $file_name = self::processUpload($files_arr[0], $target_folder, $root_images_folder);
        if (!$file_name) {
            $json_arr['status'] = 'error';
        }

        $image_path = $target_folder . DIRECTORY_SEPARATOR . $file_name;

        $json_arr['files'][] = array(
            'name' => $file_name,
            'size' => 902604,
            'url' => ImageManager::getImgUrlByFileName($image_path),
            'thumbnailUrl' => ImageManager::getImgUrlByPreset($image_path, '160_auto'),
            'deleteUrl' => "",
            'deleteType' => "DELETE"
        );

        $json_arr['status'] = 'success';

        return json_encode($json_arr);
    }

    /**
     * @param array $files_arr
     * @return array
     */
    protected static function rebuildFilesArray(array $files_arr): array
    {
        $output_files_arr = array();
        foreach ($files_arr as $key1 => $value1) {
            foreach ($value1 as $key2 => $value2) {
                $output_files_arr[$key2][$key1] = $value2;
            }
        }

        return $output_files_arr;
    }

    public static function uploadToFilesAction(): void
    {
        Exits::exit404If(!(count($_FILES) > 0));

        $file = $_FILES[0];

        $root_images_folder = $site_path = ConfWrapper::value('site_full_path') . '/images';

        $file_name = self::processUpload($file, '', $root_images_folder);

        $response = array(
            'fileName' => $file_name,
            'filePath' => $root_images_folder,
        );

        echo json_encode($response);
    }

    public static function uploadToImagesAction(): void
    {
        Exits::exit404If(!(count($_FILES) > 0));

        $file = $_FILES[0];

        $root_images_folder = ImageConstants::IMG_ROOT_FOLDER;

        $target_folder_in_images = '';

        if (array_key_exists('target_folder', $_POST)) {
            $target_folder_in_images = $_POST['target_folder'];
        }

        $file_name = self::processUpload($file, $target_folder_in_images, $root_images_folder);

        $response = array(
            'fileName' => $file_name,
            'filePath' => $root_images_folder,
        );

        header('Content-Type: application/json');

        echo json_encode($response);
    }

    /**
     * @param $file
     * @param string $target_folder_in_images
     * @param string $root_images_folder
     * @return string
     */
    public static function processUpload($file, string $target_folder_in_images, string $root_images_folder = '')
    {
        $allowed_extensions = array("gif", "jpeg", "jpg", "png");
        $allowed_types = array("image/gif", "image/jpeg", "image/jpg", "image/pjpeg", "image/x-png", "image/png");

        $pathinfo = pathinfo($file["name"]);
        $file_extension = mb_strtolower($pathinfo['extension']);

        Exits::exit404If(!in_array($file["type"], $allowed_types));
        Exits::exit404If(!in_array($file_extension, $allowed_extensions));

        Exits::exit404If($file["error"] > 0);


        $image_manager = new ImageManager($root_images_folder);
        $internal_file_name = $image_manager->storeUploadedImageFile($file["name"], $file["tmp_name"],
            $target_folder_in_images);
        Exits::exit404If(!$internal_file_name);

        return $internal_file_name;
    }
}
