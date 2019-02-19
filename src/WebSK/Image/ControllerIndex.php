<?php

namespace WebSK\Image;

/**
 * Class ControllerIndex
 * @package WebSK\Image\Image
 */
class ControllerIndex
{

    /**
     * @param string $presetName
     * @param string $imageName
     */
    public function indexAction(string $presetName, string $imageName)
    {
        $image = new ImageManager();

        $baseUrl = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        $image->output($baseUrl);
    }
}
