<?php

namespace WebSK\Image;

/**
 * Class ControllerIndex
 * @package WebSK\Image\Image
 */
class ControllerIndex
{

    /**
     * @param string $preset_name
     * @param string $image_name
     */
    public function indexAction(string $preset_name, string $image_name): void
    {
        $image = new ImageManager();

        $base_url = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $image->output($base_url);
    }
}
