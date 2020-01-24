<?php

namespace WebSK\Image;

use Imagine\Gd\Imagine;
use WebSK\Utils\Exits;

/**
 * Class ImageManager
 * @package WebSK\Image\Image
 */
class ImageManager
{

    /**
     * Imagine library Imagick adapter
     * @var Imagine
     */
    protected $imagine;

    protected $error;

    protected $root_folder;

    public function __construct($root_folder = '')
    {
        $this->imagine = new Imagine();
        if (empty($root_folder)) {
            $this->root_folder = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . ImageConstants::IMG_ROOT_FOLDER;
        } else {
            $this->root_folder = $_SERVER['DOCUMENT_ROOT'] . DIRECTORY_SEPARATOR . $root_folder;
        }
    }

    /**
     * @param string $file_name
     * @return bool
     */
    public function removeImageFile(string $file_name)
    {
        $img_path = $this->getImagesRootFolder() . DIRECTORY_SEPARATOR . $file_name;

        if (file_exists($img_path)) {
            return unlink($img_path);
        }

        return false;
    }

    public function storeUploadedImageFile($file_name, $tmp_file_name, $target_folder_in_images)
    {
        if (!\is_uploaded_file($tmp_file_name)) {
            return '';
        }

        return $this->storeImageFile($file_name, $tmp_file_name, $target_folder_in_images);
    }

    public function storeImageFile($file_name, $tmp_file_name, $target_folder_in_images)
    {
        $image_path_in_images_components_arr = [];
        if ($target_folder_in_images != '') {
            $image_path_in_images_components_arr[] = $target_folder_in_images;
        }

        $unique_filename = $this->getUniqueImageName($file_name);
        $image_path_in_images_components_arr[] = $unique_filename;

        $new_name = implode(DIRECTORY_SEPARATOR, $image_path_in_images_components_arr);

        $new_path = $this->getImagesRootFolder() . DIRECTORY_SEPARATOR . $new_name;

        $destination_file_path = pathinfo($new_path, PATHINFO_DIRNAME);
        if (!is_dir($destination_file_path)) {
            if (!mkdir($destination_file_path, 0777, true)) {
                throw new \Exception('Не удалось создать директорию: ' . $destination_file_path);
            }
        }

        $file_extension = pathinfo($new_name, PATHINFO_EXTENSION);

        $tmp_dir = $this->root_folder . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($tmp_dir)) {
            if (!mkdir($tmp_dir, 0777, true)) {
                throw new \Exception('Не удалось создать директорию: ' . $tmp_dir);
            }
        }

        // уникальное случайное имя файла
        do {
            $tmp_dest_file = $tmp_dir . DIRECTORY_SEPARATOR . 'imagemanager_' . mt_rand(0, 1000000) . '.' . $file_extension;
        } while (file_exists($tmp_dest_file));

        //try {
        $image = $this->imagine->open($tmp_file_name);
        $image = ImagePresets::processImageByPreset($image, ImageConstants::DEFAULT_UPLOAD_PRESET);

        // запись во временный файл, чтобы другой процесс не мог получить доступ к недописанному файлу
        $image->save($tmp_dest_file, array());

        // переименовываем временный файл
        if (!rename($tmp_dest_file, $new_path)) {
            throw new \Exception('Не удалось переместить файл: ' . $tmp_dest_file . ' -> ' . $new_path);
        }

        return $unique_filename;
    }

    public function storeRemoteImageFile($file_url, $target_folder_in_images = '')
    {
        $new_name = $this->getUniqueImageName('temp.jpg');

        $new_path = $this->getImagesRootFolder() . DIRECTORY_SEPARATOR . $new_name;
        if ($target_folder_in_images != '') {
            $new_path = $this->getImagesRootFolder() . DIRECTORY_SEPARATOR . $target_folder_in_images . DIRECTORY_SEPARATOR . $new_name;
        }

        $image = $this->imagine->open($file_url);
        $image = ImagePresets::processImageByPreset($image, ImageConstants::DEFAULT_UPLOAD_PRESET);
        $image->save($new_path, []);

        return $new_name;
    }

    public function output($file_url)
    {
        list($image_name, $preset_name) = $this->acquirePresetNameAndImageNameFromUrl($file_url);
        $fullpath = $this->getImagePathByPreset($image_name, $preset_name);

        if (!file_exists($fullpath)) {
            $image_path = $this->getImagesRootFolder() . DIRECTORY_SEPARATOR . $image_name;

            if (!file_exists($image_path)) {
                Exits::exit404();
            }

            $res = $this->moveImageByPreset($image_path, $fullpath, $preset_name);
        }
        $ext = pathinfo($fullpath, PATHINFO_EXTENSION);

        $fp = fopen($fullpath, 'rb');
        header("Content-Type: image/" . $ext);
        header("Content-Length: " . filesize($fullpath));
        fpassthru($fp);
        exit;
    }

    public function moveImageByPreset($image_path, $preset_path, $preset_name)
    {
        $image = $this->imagine->open($image_path);

        $preset_dir = dirname($preset_path);

        if (!\file_exists($preset_dir)) {
            $res = mkdir($preset_dir, 0777, true);
            if (!$res) {
                $this->error = "Unable to create path: " . $preset_dir;
                return false;
            }
        }

        $file_extension = pathinfo($preset_path, PATHINFO_EXTENSION);

        $tmp_dir = $this->root_folder . DIRECTORY_SEPARATOR . 'tmp';
        if (!is_dir($tmp_dir)) {
            if (!mkdir($tmp_dir, 0777, true)) {
                throw new \Exception('Не удалось создать директорию: ' . $tmp_dir);
            }
        }

        // уникальное случайное имя файла
        do {
            $tmp_dest_file = $tmp_dir . DIRECTORY_SEPARATOR . 'imagemanager_' . mt_rand(0, 1000000) . '.' . $file_extension;
        } while (file_exists($tmp_dest_file));

        $image = ImagePresets::processImageByPreset($image, $preset_name);

        // запись во временный файл, чтобы другой процесс не мог получить доступ к недописанному файлу
        $image->save($tmp_dest_file, array('quality' => 100));

        // переименовываем временный файл
        if (!rename($tmp_dest_file, $preset_path)) {
            throw new \Exception('Не удалось переместить файл: ' . $tmp_dest_file . ' -> ' . $preset_path);
        }

        return true;
    }

    public function getUniqueImageName($user_image_name)
    {
        $ext = pathinfo($user_image_name, PATHINFO_EXTENSION);
        $image_name = str_replace(".", "", uniqid(md5($user_image_name), true)) . "." . $ext;

        return $image_name;
    }

    public function getImagePathByPreset($image_name, $preset_name)
    {
        $images_path_in_filesystem = $this->getImagesRootFolder();
        return
            $images_path_in_filesystem
            . DIRECTORY_SEPARATOR
            . ImageConstants::IMG_PRESETS_FOLDER
            . DIRECTORY_SEPARATOR
            . $preset_name
            . DIRECTORY_SEPARATOR
            . $image_name;
    }

    public function acquirePresetNameAndImageNameFromUrl($requested_file_path)
    {
        $requested_file_path = ltrim($requested_file_path, '/');

        $file_path_parts_arr = explode(ImageConstants::IMG_PRESETS_FOLDER . '/', $requested_file_path);
        $image_path_parts_arr = explode('/', $file_path_parts_arr[1]);
        $preset_name = array_shift($image_path_parts_arr);
        $file_path_relative = implode('/', $image_path_parts_arr);

        return [$file_path_relative, $preset_name];
    }

    public function getImagesRootFolder()
    {
        return $this->root_folder;
    }

    public static function getImgUrlByPreset($image_name, $preset_name)
    {
        $preset_url = self::getPresetUrlByName($preset_name);
        $image_url = $preset_url . DIRECTORY_SEPARATOR . $image_name;

        return $image_url;
    }

    public static function getImgUrlByFileName($image_name)
    {
        return DIRECTORY_SEPARATOR . ImageConstants::IMG_ROOT_FOLDER . DIRECTORY_SEPARATOR . $image_name;
    }

    public static function getPresetUrlByName($preset_name)
    {
        return DIRECTORY_SEPARATOR . ImageConstants::IMG_ROOT_FOLDER . DIRECTORY_SEPARATOR . ImageConstants::IMG_PRESETS_FOLDER . DIRECTORY_SEPARATOR . $preset_name;
    }
}
