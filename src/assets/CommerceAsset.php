<?php

namespace Simp\Commerce\assets;

class CommerceAsset
{
    protected string $contentCss;
    protected string $contentJs;
    public function __construct()
    {
        $this->contentCss = "";
        $this->contentJs = "";

        $default_source = __DIR__. "/assets";

        if (is_dir($default_source)) {

            foreach (ASSETS_CSS as $file) {
                $fullname = $default_source . "/" . $file;
                if (file_exists($fullname)) {
                    $cc = file_get_contents($fullname);
                    $this->contentCss .= "<style>{$cc}</style>".PHP_EOL;
                }
            }

            foreach (ASSETS_JS as $file) {
                $fullname = $default_source . "/" . $file;
                if (file_exists($fullname)) {
                    $cc = file_get_contents($fullname);
                    $nonce = bin2hex(random_bytes(16));
                    $this->contentJs .= "<script nonce='{$nonce}'>{$cc}</script>".PHP_EOL;
                }
            }

        }

    }

    public function getContentCss(): string
    {
        return $this->contentCss;
    }

    public function getContentJs(): string
    {
        return $this->contentJs;
    }

}