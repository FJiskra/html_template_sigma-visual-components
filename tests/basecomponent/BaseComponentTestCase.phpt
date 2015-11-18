<?php
/**
 * Created by PhpStorm.
 * User: jiskra
 * Date: 8.4.2015
 * Time: 23:54
 */
use ANT\Components\__ROOT__Component;
use Nette\Caching\Cache;
use Tester\Assert;

require_once '../../bootstrap.php';

class BaseComponentTestCase extends Tester\TestCase
{
    private static $component;

    function getBasicTestComponent()
    {
        $tc = new TestComponent();
        $tc->setTemplateDir(__DIR__ . '/tpl/components');

        $sigma = new \HTML_Template_Sigma($tc->getTemplateDir(), __DIR__ . '/cache/sigma');
        $tc->setTemplate($sigma);

        return $tc;
    }

    function getBasicTestChildComponent()
    {
        $tc = new TestChildComponent();
        $tc->setTemplateDir(__DIR__ . '/tpl/components');

        $sigma = new \HTML_Template_Sigma($tc->getTemplateDir(), __DIR__ . '/cache/sigma');
        $tc->setTemplate($sigma);

        return $tc;
    }

    /**
     * @param \ANT\Components\BaseComponent $c
     * @return \ANT\Components\BaseComponent
     */
    function getBasicComponent(\ANT\Components\BaseComponent $c)
    {
        $tc = $c;
        $tc->setTemplateDir(__DIR__ . '/tpl/components');

        $sigma = new \HTML_Template_Sigma($tc->getTemplateDir(), __DIR__ . '/cache/sigma');
        $tc->setTemplate($sigma);

        return $tc;
    }

    /**
     * @return \Nette\Caching\Cache
     */
    protected function getBasicCache()
    {
        $j = new \Nette\Caching\Storages\FileJournal(__DIR__ . '/cache');
        $s = new \Nette\Caching\Storages\FileStorage(__DIR__ . '/cache', $j);
        return new \Nette\Caching\Cache($s);
    }

    /**
     *
     */
    function testBasic()
    {
        $cache = $this->getBasicCache();

        $kesfilename = TestComponent::modifyName(TestComponent::getClassName());

        Assert::equal(
            'testcomponent',
            $kesfilename
        );

        $cache->clean(array(
            Cache::TAGS => array($kesfilename)
        ));

        $finder = new \Nette\Utils\Finder();
        $count = $finder->findFiles('_*')->in(__DIR__ . '/cache')->count();
        Assert::equal(0, $count);

        $c = $this->getBasicTestComponent();

        Assert::exception(function () use ($c) {
            $c->toString();
        }, '\Exception');
        $c->setParentComponent(new __ROOT__Component());

        $c->setChachingEnabled(false);
        $c->setParentChachingEnabled(false);
        Assert::equal($c->toString(), 'testHTMLcontent ');

        Assert::equal(
            'default',
            $c->getCacheNamePostfix()
        );
        Assert::equal(
            'testcomponent_' . $c->getCacheNamePostfix(),
            $c->getCacheFileName()
        );
        Assert::null($cache->load($c->getCacheFileName()));

        $c->setChachingEnabled(true);
        $c->setParentChachingEnabled(false);
        $c->setCache($cache);
        Assert::equal($c->getTemplateResult(), 'testHTMLcontent ');
        $c->setTemplateResult(null);

        $options = $c->getCachingOptions();
        $expectOptions['tags'] = ['testcomponent'];
        Assert::equal($expectOptions, $options);

        $c->toString(); //spusti cachovani
        Assert::notEqual(null, $cache->load($c->getCacheFileName()));

        TestComponent::removeAllCachedInstances($cache);
        $count = $finder
            ->findFiles('_*')
            ->in(__DIR__ . '/cache')
            ->count();
        Assert::equal(0, $count);
    }

    /**
     */
    function testChildComponentParsing()
    {
        $parent = $this->getBasicComponent(new SecondTestComponent());
        $cc = $this->getBasicComponent(new TestChildComponent());
        $parent->setTemplateFile('TestComponent');
        $parent->addComponent($cc);

        $except = ['TestChildComponent' => $cc];
        Assert::equal($except, $parent->getChildComponents());

        Assert::exception(function () use ($parent) {
            $parent->toString();
        }, '\Exception');

        $parent->setParentComponent(new __ROOT__Component());
        Assert::exception(function () use ($parent) {
            $parent->toString();
        }, '\Exception');

        $cc->setParentComponent($parent);
        $except = ['TestChildComponent' => $cc];
        Assert::equal($except, $parent->getChildComponents());
        Assert::equal('testHTMLcontent obsah Child komponenty', $parent->toString());
    }
}

class SecondTestComponent extends \ANT\Components\BaseComponent
{
    function parseTemplate()
    {
        $tcc = $this->getChildComponents();
        Assert::count(1, $tcc);
        $this->getTemplate()->setGlobalVariable('TestChildComponent', $tcc['TestChildComponent']->toString());
//        $this->parseChildComponents();
        return $this->getTemplate()->get();
    }

    public function __construct()
    {
        parent::__construct('default');
        $this->setParentChachingEnabled(false);
        $this->setChachingEnabled(false);
    }
}

class TestComponent extends \ANT\Components\BaseComponent
{
    function parseTemplate()
    {
        return $this->getTemplate()->get();
    }

    public function getCacheFileName()
    {
        return parent::getCacheFileName();
    }

    public function __construct()
    {
        parent::__construct('default');
        $this->setParentChachingEnabled(false);
        $this->setChachingEnabled(false);
    }
}

class TestChildComponent extends \ANT\Components\BaseComponent
{

    function parseTemplate()
    {
        return $this->getTemplate()->get();
    }

    public function getCacheFileName()
    {
        return parent::getCacheFileName();
    }

    public function __construct()
    {
        parent::__construct('default');
        $this->setParentChachingEnabled(false);
        $this->setChachingEnabled(false);
    }
}

$testcase = new BaseComponentTestCase();
$testcase->run();