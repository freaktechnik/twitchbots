<?php
require '../vendor/autoload.php';
namespace Mini\Twig\Extension;

class GeshiExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            'geshi' => new \Twig_Filter_Method($this, 'geshiHighlight'),
        );
    }

    public function geshiHighlight($source, $language)
    {
        $geshi = new \GeSHi($source, $language);
        $geshi->enable_classes();

        return $geshi->parse_code();
    }

    public function getName()
    {
        return 'geshi_highlight';
    }
}
?>
