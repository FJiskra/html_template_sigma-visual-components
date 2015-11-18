<?php
/**
 * Created by PhpStorm.
 * User: jiskra
 * Date: 19.3.2015
 * Time: 23:30
 */

namespace ANT\Components;

use Nette\Http\Request;
use Nette\Http\UrlScript;
use Nette\Utils\Paginator;

class BetterPaginatorComponent extends BaseComponent implements IComponent
{
    /**
     * @var Request
     */
    public $_request;
    public $localizeCallback;
    public $urlFragment;
    /**
     * @var Paginator
     */
    protected $pagingLogic;
    protected $tpl;

    public function __construct($name = 'default')
    {
        parent::__construct($name);
        $this->setChachingEnabled(false);
        $this->setParentChachingEnabled(true);
        $this->setCache(\App::getContainer()->getBaseCache());
        $this->setTemplateFile($this->getReflection()->getShortName());

        $this->pagingLogic = new Paginator();
        $this->pagingLogic->setBase(1);
        $this->pagingLogic->setItemsPerPage(10);
    }

    /**
     * @return Paginator
     */
    public function getPagingLogic()
    {
        return $this->pagingLogic;
    }

    function getPars()
    {

    }

    public function setDefaultLocalizeCallback()
    {
        $this->localizeCallback = function ($tpl) {
            return \App::localize($tpl);
        };
    }

    function initPage()
    {
        $this->pagingLogic->setPage($this->getCurrentPage());
    }

    function getCurrentPageVariableName()
    {
        $cacheFileName = $this->getCacheNamePostfix();
        $_GET_VarName = $cacheFileName . '-curr';
        return $_GET_VarName;
    }

    function getCurrentPage()
    {
        $currentPage = $this->_request->getQuery($this->getCurrentPageVariableName(), $this->pagingLogic->getBase());
        return $currentPage;
    }

    function getNextPage()
    {
        $curr = $this->pagingLogic->getPage();
        $this->pagingLogic->setPage($curr + 1);
        $next = $this->pagingLogic->getPage();
        $this->pagingLogic->setPage($curr);
        return $next;
    }

    function getPreviousPage()
    {
        $firs = $this->pagingLogic->getFirstPage();
        $curr = $this->pagingLogic->getPage();
        return ($curr <= $firs) ? ($firs) : ($curr - 1);
    }

    function buildURL($curr = null)
    {
        if ($curr == null) {
            $curr = $this->pagingLogic->getBase();
        }

        $allGetVars = $this->_request->getQuery();
        $urlScript = new UrlScript();
        $urlScript->setQuery($allGetVars);
        $urlScript->setQueryParameter($this->getCurrentPageVariableName(), $curr);
//        $urlScript->setFragment($this->_request->getUrl()->getFragment());
        return $urlScript->getRelativeUrl() . $this->getUrlFragment();
    }

    /**
     * @return string
     */
    public function getUrlFragment()
    {
        return $this->urlFragment == null ? '' : '#' . $this->urlFragment;
    }

    protected function parseTemplate()
    {
        $this->tpl = $this->getTemplate();

        if (is_callable($this->localizeCallback)) {
            $calable = $this->localizeCallback;
            $this->tpl = $calable($this->tpl);
        }

        $this->tpl->setGlobalVariable('index', URL_INDEX);
        $this->tpl->setGlobalVariable('root', URL_ROOT);

        $this->tpl->setCurrentBlock('base');

        $fnd = $this->pagingLogic->getItemCount();
        $cnt = $this->pagingLogic->getItemsPerPage();
        $pgs = $this->pagingLogic->getPageCount();

        $curr = $this->pagingLogic->getPage();
        $firs = $this->pagingLogic->getFirstPage();
        $last = $pgs;
        $next = $this->getNextPage();
        $prev = $this->getPreviousPage();

        $this->tpl->setVariable('first', $this->buildURL($firs));
        $this->tpl->setVariable('prev', $this->buildURL($prev));
        $this->tpl->setVariable('next', $this->buildURL($next));
        $this->tpl->setVariable('last', $this->buildURL($last));

        $this->tpl->setVariable('rows', $cnt);
        $this->tpl->setVariable('found', $fnd);
        $this->tpl->setVariable('page', $curr);
        $this->tpl->setVariable('pages', $pgs);

        $this->tpl->parseCurrentBlock();
        return $this->tpl->get();
    }
}