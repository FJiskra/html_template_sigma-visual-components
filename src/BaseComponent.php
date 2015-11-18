<?php
/**
 * Created by PhpStorm.
 * User: jiskra
 * Date: 17.3.2015
 * Time: 11:14
 */

namespace ANT\Components;

use Nette\Caching\Cache;
use Nette\Utils\Arrays;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Nette\Utils\Validators;
use Tracy\Debugger;

/** usage: implementuju parseTemplate metodu, ktera vraci html string
 * implementuju constructor, ve kterym vyvolam parentsky constructor. Nasetuju templateFile. Zakazu keš (treba). Nastavim moznosti kesovani.
 *
 * Class BaseComponent
 * @package ANT\Components
 */
abstract class BaseComponent implements IComponent
{
    /** prvni je nazev tridy reprezentujici komponentu: \Nette\Utils\Strings::webalize($this->getReflection()->getName());
     * druhy je postFix, ktery bohuzel neni staticky, protože je to ID instance
     * @var string
     */
    private static $cacheFileNamePatttern = "%s_%s";
    /**
     * @var \ReflectionClass
     */
    private $reflection;
    /** když smazu detskou kes tak chci aby automaticky se smazala i keš rodiče. Proto je musim sparovat.
     * @var array
     */
    private $childComponentsCacheFileNames = array();
    /** instance šablony. pouziva se podobne jako latte nebo smarty
     * @var \HTML_Template_Sigma template itself
     */
    private $template;
    private $templateDir;
    private $templateFile;
    private $templateResult;
    /** všechny dětské komponenty, které se v teto komponente volaji
     *
     * @var IComponent|string[][]
     */
    private $childComponents = array();
    /** rodic ve kterem jsem vytvaren. Tomu musim rict jestli mě má do sebe nakešovat.
     * @var IComponent
     */
    private $parentComponent;
    /** libka od nette.org. Loaduje se z composeru.
     * @var \Nette\Caching\Cache
     */
    private $cache;
    /** všechny detske komponenty musi souhlasit s kešovanim teto (parentske) komponenty
     * @var boolean
     */
    private $isChachingAgreedByAllChildComponents;
    /** bude sama sebe tato komponenta kešovat ?
     * @var bool
     */
    private $chachingEnabled = null;
    /** Bude dovoleno rodiči (vnější komponenta), aby mě nakešoval ?
     * @var bool
     */
    private $parentChachingEnabled = true;

    /** přípona názvu souboru pro samotny cache soubor. kdyz chci kešovat pro každou instanci a ne jen pro komponentu jako jednu třídu
     * @var string
     */
    private $cacheNamePostfix;
    /** název souboru reprezentujici keš. všechny keše jsou v jedný složce, takže pozor na kolize!
     *  Skládá se z názvu komponenty, která dědí z BaseComponent, a PostFixu, který je volitelný. Možná že při složitém dědění se název zjistí blbě.
     * @var string
     */
    private $cacheFileName;
    /** Nette možnosti pri ulozeni keše
     * @var array
     */
    private $cachingOptions;

    /** predtim než rodič začne sam sebe kešovat, zepta se jestli s tím souhlasi všechny vnořené komponenty.
     * Pokud ne, tak se nenakešuje.
     * @param mixed $isChachingAgreedByAllChildComponents
     */
    public function setIsChachingAgreedByAllChildComponents($isChachingAgreedByAllChildComponents)
    {
        $this->isChachingAgreedByAllChildComponents = $this->isChachingAgreedByAllChildComponents() && $isChachingAgreedByAllChildComponents;
//        $aktualStav = $this->isChachingAgreedByAllChildComponents();
//        if ($aktualStav === null || $aktualStav === TRUE)
//            $this->isChachingAgreedByAllChildComponents = $isChachingAgreedByAllChildComponents;
    }

    /** vrátí název pro kešovaný soubor.
     *  tady  se  resi problem odlišnych nazvu instanci stejne tridy.
     * CacheNamePostFix je jedinečný identifikátor instance (nutne pro kesovani).
     */
    protected function getCacheFileName()
    {
        if ($this->cacheFileName == null) {
            $name = $this->getModifiedName();
            $cacheFileName = sprintf(self::getCacheFileNamePatttern(),
                $name,
                $this->getCacheNamePostfix()
            );
            $this->setCacheFileName($cacheFileName);
        }

        if (mb_strlen($this->cacheFileName) == 0 || $this->cacheFileName == null)
            throw new \Exception('nemuzes mit prazdny nazev');

        return $this->cacheFileName;
    }

    protected function setCacheFileName($cacheFileName)
    {
        if (mb_strlen($cacheFileName) == 0)
            throw new \Exception('nemuzes mit prazdny nazev');
        $this->cacheFileName = $cacheFileName;
    }

    /**
     * @return mixed
     */
    public function getCacheNamePostfix()
    {
        return $this->cacheNamePostfix;
    }

    public function setParentComponent(BaseComponent &$component)
    {
        if ($component instanceof __ROOT__Component) {
            $this->parentComponent = $component;
        } elseif ($component instanceof BaseComponent) {
            $this->parentComponent = $component;
            $this->parentComponent->setIsChachingAgreedByAllChildComponents($this->isParentChachingEnabled());
            if ($this->isChachingEnabled()) {
                $this->parentComponent->addChildComponentCacheFileName($this->getCacheFileName());
            }
        } else {
            throw new \Exception('Pokud nejsi v komponente predej argumentem __ROOT__Component');
        }
    }

    public function __construct($cacheFileNamePostfix)
    {
        $name = get_class($this);
        $rc = new \ReflectionClass($name);
        $this->setReflection($rc);
        $this->setCacheFileNamePostfix($cacheFileNamePostfix);

        $this->setTemplateDir(__DIR__ . '/../../tpl/components');
        if (!is_dir($this->getTemplateDir()))
            throw new \Exception('Nenašlo to slozku pro sablony komponent: ' . $this->getTemplateDir());

        $sigma = new \HTML_Template_Sigma($this->getTemplateDir(), __DIR__ . '/../../cache/sigma');
        $this->setTemplate($sigma);
    }


    /** pokud chci kesovat musim objektu predat instanci Nette\Cache pres metodu setCache
     * @return string
     * @throws \Exception
     */
    public function toString()
    {
        if ($this->parentComponent == null)
            throw new \Exception('musis nejdrive predat komponente svoji instanci komponenty metodou BaseComponent::setParentComponent()
             (kvuli vypinani kesovani vnorenych komponent). Pokud nejsi komponenta, vloz __ROOT__Component');

//        if ($this->isChachingEnabled()) {
//            if ($this->getCache() == null)
//                throw new \Exception('Chces kesovat, ale nemas libku na kesovani!');

        if ($this->getTemplateResult() == null) {
            $this->setTemplateResult($this->cacheComponent());
        }
        return $this->getTemplateResult();
//        } else {
//            if ($this->getTemplateResult() == null) {
//                $this->loadTemplateFile();
//                $this->setTemplateResult($this->parseTemplate());
//            }
//            return $this->getTemplateResult();
//        }

    }

    /**
     * TODO mám nakešovane detske komponenty, ktere nakonec nevyuziju, protoze jejich obsah je soucasti keše parentske komponenty
     * je to chyba? Tato keš se využije pri opetovnem generovani parentske keše.
     * @return mixed|NULL|void
     * @throws \Exception
     */
    protected function cacheComponent()
    {
        $this->event_cacheComponentStart();
        $v = null;
        $name = $this->getCacheFileName();

    if($this->isChachingEnabled()) {
        if (!$this->getCache() instanceof Cache)
            throw new \Exception('Chces kesovat, ale nemas libku na kesovani!');

        if ($this->getCache() instanceof Cache)
            $v = $this->getCache()->load($name);
    }

        if ($v === null || !$this->isChachingEnabled()) {
            $this->loadTemplateFile();
            $this->event_beforeParseTemplate();
            $v = $this->parseTemplate();

            if ($v instanceof \PEAR_Error)
                throw new \Exception('Mas nastavenej templateFile? MSG: ' . $v->message);

            if ($this->isChachingAgreedByAllChildComponents() && $this->isChachingEnabled()) {
                $this->event_beforeCacheSave();

                $dateNOW = new \DateTime();
                $dateNOW = $dateNOW->format('Y-m-d H:i:s');
                $options = $this->getCachingOptions();
                $netteDatetime = DateTime::from($options[Cache::EXPIRE]);
                $comment = 'cached: ' . $dateNOW . ' expire: ' . $netteDatetime->format('Y-m-d H:i:s');

                $this->wrapWithComment($v, $comment);

                Debugger::barDump('kesuju instanci komponenty s nazvem: ' . $name);
                $this->getCache()->save(
                    $name,
                    $v,
                    $options
                );
            } else {
                $this->wrapWithComment($v);
            }
        }

        return $v;
    }

    /**
     *  mam nasetovany dependence a nezjistil jsem kes
     */
    protected function event_cacheComponentStart()
    {
    }
    protected function event_beforeParseTemplate()
    {
    }
    /** dělam tady addCachingOption pro expiraci keše
     *  až po parseTemplate()
     */
    protected function event_beforeCacheSave()
    {
    }

    /** nazev sablony v tpl/components/  bez pripony
     * @param mixed $templatePath
     */
    public function setTemplateFile($templatePath)
    {
        if (is_string($templatePath)) {
            $endsWithHtml = \Nette\Utils\Strings::endsWith($templatePath, '.html');
            if (!$endsWithHtml) {
                $templatePath .= '.html';
            }
            $this->templateFile = $this->getTemplateDir() . '/' . $templatePath;
        }
        if (!is_file($this->getTemplateFile()))
            throw new \Exception('sablona komponenty neexistuje: ' . $this->getTemplateFile());
    }

    /**naplní proměnné sablony .
     *Nezapomen parsovat pridane komponenty
     * $tpl->setCurrentBlock("inner1"); $tpl->setVariable(...); $tpl->parseCurrentBlock();
     * $tpl->setVariable(...); $tpl->parseCurrentBlock();
     * $tpl->parse("block2");
     *
     * @return void
     */
    protected function parseTemplate()
    {
        throw new \Exception('Metodu: ' . __METHOD__ . ' musis sam implementovat!');
    }

    /**
     * @param IComponent $component jakakoliv komponenta, ktera se bude vykreslovat uvnitr teto komponenty
     * všechny dependence pro komponentu musi byt nasetovany pred tim nez to sem loupnes
     * @param null $placeholder jaky je nazev promeny v sablone, do ktery se nacpe HTML string komponenty, kterou prave predavas.
     * Pokud je null pouzije se nazev tridy $component
     */
    public function addComponent(IComponent &$component, $placeholder = null)
    {
        if ($placeholder === null)
            $placeholder = get_class($component);

        $cc = $this->getChildComponents();
        $cc[$placeholder] = $component;
        $this->setChildComponents($cc);
    }

    /**@deprecated
     * @throws \Exception
     */
    protected function parseChildComponents()
    {
        foreach ($this->getChildComponents() as $placeholder => $component) {
            if (!$this->getTemplate()->placeholderExists($placeholder))
                throw new \Exception('placeholder: ' . $placeholder . ' se v sablone nevyskytuje');

            $this->getTemplate()->setGlobalVariable($placeholder, $component->toString());
        }
    }

    public static function getClassName()
    {
        return get_called_class();
    }

    public function setCache(\Nette\Caching\Cache $cache)
    {
        $this->cache = $cache;
    }

    /**
     * @return \Nette\Caching\Cache
     */
    public function getCache()
    {
        return $this->cache;
    }

    /**
     * @return \HTML_Template_Sigma
     */
    public function &getTemplate()
    {
        return $this->template;
    }

    /**
     * @return mixed
     */
    public function getTemplateDir()
    {
        return $this->templateDir;
    }

    /**
     * @return mixed
     */
    public function getTemplateFile()
    {
        if ($this->templateFile == null) {
            $this->setTemplateFile($this->getReflection()->getShortName());
        }
        if ($this->templateFile == null) {
            throw new \Exception('nemas natavenou sablonu pres setTemplateFile');
        }

        return $this->templateFile;
    }

    private function loadTemplateFile()
    {
        $r = $this->getTemplate()->loadTemplateFile(basename($this->getTemplateFile()));
        if ($r != SIGMA_OK)
            throw new \Exception('Sigme vadi sablona pro komponentu: ' . $this->getTemplateFile());
    }

    /**
     * @return mixed
     */
    public function getTemplateResult()
    {
        return $this->templateResult;
    }

    /**
     * @return IComponent|string[][]
     */
    public function getChildComponents()
    {
        return $this->childComponents;
    }

    /**
     * @param \HTML_Template_Sigma $template
     */
    public function setTemplate($template)
    {
        $this->template = $template;
    }

    /**
     * @param mixed $templateDir
     */
    public function setTemplateDir($templateDir)
    {
        $this->templateDir = $templateDir;
    }

    /**
     * @param mixed $templateResult
     */
    public function setTemplateResult($templateResult)
    {
        if ($templateResult instanceof \PEAR_Error) {
            throw new \Exception($templateResult->message);
        }

        $this->templateResult = $templateResult;
    }

    /**
     * @param IComponent|\string[][] $childComponents
     */
    public function setChildComponents($childComponents)
    {
        $this->childComponents = $childComponents;
    }

    /**
     * @return boolean
     */
    public function isChachingEnabled()
    {
        return $this->chachingEnabled == null ? false : $this->chachingEnabled;
    }

    /**
     * @param boolean $chachingEnabled
     */
    public function setChachingEnabled($chachingEnabled)
    {
        $this->chachingEnabled = $chachingEnabled;
    }

    /**
     * @return IComponent
     */
    public function getParentComponent()
    {
        return $this->parentComponent;
    }

    /** POZOR pokud komponenta pouziva jinou keš než BaseCache, tak ji to musis predat!!!!
     * @param null $cache
     */
    public static function removeAllCachedInstances($cache = null)
    {
        if ($cache == null) {
            $cache = \App::getContainer()->getBaseCache();
        }

        $cache->clean(array(
            Cache::TAGS => array(self::modifyName(self::getClassName()))
        ));
    }

    public function removeCachedFile(){
        $cache = $this->getCache(); // pravdepodobne bookCache !!!!
        $cache->remove($this->getCacheFileName()); // pozor na to nad jakou Cache servicou to volas
    }

    public static function modifyName($name)
    {
        return \Nette\Utils\Strings::webalize($name);
    }

    public function getModifiedName()
    {
        return self::modifyName($this->getReflection()->getName());
    }

    private function array_merge($arr1, $arr2)
    {
        if (!is_array($arr1)) {
            if (empty($arr1)) {
                $arr1 = array();
            } else {
                $arr1 = array($arr1);
            }
        }
        if (!is_array($arr2)) {
            if (empty($arr2)) {
                $arr2 = array();
            } else {
                $arr2 = array($arr2);
            }
        }

        return array_merge($arr1, $arr2);
    }

    /**
     * @return array
     */
    public function getCachingOptions()
    {
        $childComponentsCacheFileNames = $this->getChildComponentsCacheFileNames();

        $additionalOptionTags = array($this->getModifiedName());
        $this->addCachingOption($additionalOptionTags);

        if (!empty($childComponentsCacheFileNames)) {
            $this->cachingOptions[Cache::ITEMS] = $this->array_merge($this->cachingOptions[Cache::ITEMS], $childComponentsCacheFileNames);
        }

        return $this->cachingOptions;
    }

    public function addCachingOption($additionalOption, $section = Cache::TAGS)
    {
        $makeArray = true;

        switch ($section) {
            case Cache::SLIDING:
            case Cache::EXPIRE:
                $makeArray = false;
        }

        if ($makeArray) {
            $this->cachingOptions[$section] = $this->array_merge($this->cachingOptions[$section], $additionalOption);
        } else {
            $this->cachingOptions[$section] = $additionalOption;
        }
    }

    /**
     *  array(
     * Cache::TAGS => array('kategorie'),
     * Cache::EXPIRE => '90 minutes'
     * ));
     * @param array $cachingOptions
     */
    public function setCachingOptions(array $cachingOptions)
    {
        $this->cachingOptions = $cachingOptions;
    }

    /** normalne se keš uklada pod nazvem tridy. jenže když mám několik instanci tak potřebuje ještě něco. třeba id knihy. Pak to uložit jako TAG nebo do jmena
     * @param mixed $cacheNamePostfix
     */
    public function setCacheFileNamePostfix($cacheNamePostfix)
    {
        $this->cacheNamePostfix = $cacheNamePostfix;
    }

    /** souhlasí všechny destske komponenty s tim aby byly nakešované do parentske keše ?
     * @return boolean
     */
    protected function isChachingAgreedByAllChildComponents()
    {
        return $this->isChachingAgreedByAllChildComponents == null ? true : $this->isChachingAgreedByAllChildComponents;
    }

    /**
     * @return boolean
     */
    public function isParentChachingEnabled()
    {
        return $this->parentChachingEnabled;
    }

    /** Chci aby muj parent mel moznost me do sebe nakesovat?
     * @param boolean $parentChachingEnabled
     */
    public function setParentChachingEnabled($parentChachingEnabled)
    {
        $this->parentChachingEnabled = $parentChachingEnabled;
    }

    /**
     * @return \ReflectionClass
     */
    public function getReflection()
    {
        if ($this->reflection->getName() == null || mb_strlen($this->reflection->getName()) == 0)
            throw new \Exception('nepodarilo se mi zjistit nazev komponenty. Nededis z nejake jine komponenty?');

        if ($this->reflection == null)
            throw new \Exception('reflekse je prazdna. Asi to nenaslo tridu.');

        return $this->reflection;
    }

    /**
     * @param \ReflectionClass $reflection
     */
    public function setReflection($reflection)
    {
        $this->reflection = $reflection;
    }

    /**
     * @return array
     */
    public function getChildComponentsCacheFileNames()
    {
        return $this->childComponentsCacheFileNames;
    }

    /**
     * @param array $childComponentsNames
     */
    public function setChildComponentsCacheFileNames($childComponentsNames)
    {
        $this->childComponentsCacheFileNames = $childComponentsNames;
    }

    /**
     * @param array $childComponentsNames
     */
    public function addChildComponentCacheFileName($childComponentName)
    {
        $this->childComponentsCacheFileNames[] = $childComponentName;
    }

    public static function setCacheFileNamePatttern($cacheFileNamePatttern)
    {
        throw new \Exception('nepresuj $cacheFileNamePatttern, pak v tom bude chaos při mazani');
    }

    public static function getCacheFileNamePatttern()
    {
        return self::$cacheFileNamePatttern;
    }

    /** <!-- BEGIN COMPONENT ...
     * @param $v string HTML. REFERENCE
     * @param $comment string
     * @param $commentEnd string
     */
    protected function wrapWithComment(&$v, $comment = '', $commentEnd = null)
    {
        $commentEnd = $commentEnd == null ? $comment : $commentEnd;
        $name = $this->getCacheFileName();
        $valuePrefix = "\n<!-- BEGIN COMPONENT: $name $comment -->\n";
        $valuePostfix = "\n<!-- END COMPONENT: $name $commentEnd -->\n";

        $v = $valuePrefix . $v . $valuePostfix;
    }
}