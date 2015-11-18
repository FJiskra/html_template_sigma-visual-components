<?php
/**
 * Created by PhpStorm.
 * User: jiskra
 * Date: 18.3.2015
 * Time: 22:12
 */

namespace ANT\Components;

/** Instanciuj pouze pokud nejsi v komponentě. pres setParentComponent předavej $this, pokud $this is_a BaseComponent
 * Class __ROOT__Component
 * @package ANT\Components
 */
class __ROOT__Component extends BaseComponent{

    /**
     * @return string vrátí zparsovaný template komponenty ve formě stringu
     */
    function toString()
    {
        // TODO: Implement toString() method.
    }

    /**
     * @param IComponent $component jakakoliv komponenta, ktera se bude vykreslovat uvnitr teto komponenty
     * všechny dependence pro komponentu musi byt nasetovany pred tim nez to sem loupnes
     * @param null $placeholder jaky je nazev promeny v sablone, do ktery se nacpe HTML string komponenty, kterou prave predavas.
     * Pokud je null pouzije se nazev tridy $component
     */
    function addComponent(IComponent &$component, $ploceholder = null)
    {
        // TODO: Implement addComponent() method.
    }

    /**chci kešovat komponentu?
     * @return boolean
     */
    function isCachingEnabledByDefault()
    {
        // TODO: Implement isCachingEnabledByDefault() method.
    }

    function __construct(){

    }
}