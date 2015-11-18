<?php
/**
 * Created by PhpStorm.
 * User: jiskra
 * Date: 17.3.2015
 * Time: 11:32
 */

namespace ANT\Components;


interface IComponent
{
    /**
     * @return string vrátí zparsovaný template komponenty ve formě stringu
     */
    function toString();

    /**
     * @param IComponent $component jakakoliv komponenta, ktera se bude vykreslovat uvnitr teto komponenty
     * všechny dependence pro komponentu musi byt nasetovany pred tim nez to sem loupnes
     * @param null $placeholder jaky je nazev promeny v sablone, do ktery se nacpe HTML string komponenty, kterou prave predavas.
     * Pokud je null pouzije se nazev tridy $component
     */
//    function addComponent(IComponent &$component, $ploceholder = null);
}