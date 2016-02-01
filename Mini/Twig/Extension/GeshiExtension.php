<?php
namespace Mini\Twig\Extension;

require '../vendor/autoload.php';

class GeshiExtension extends \Twig_Extension
{
    public function getFilters()
    {
        return array(
            'geshi' => new \Twig_Filter_Method($this, 'geshiHighlight', array('is_safe' => array('html')))
        );
    }

    public function geshiHighlight($source, $language)
    {
        $geshi = new \GeSHi($source, $language);
        //$geshi->enable_classes();

        return $geshi->parse_code();
    }

    public function getName()
    {
        return 'geshi_highlight';
    }
}
?>
