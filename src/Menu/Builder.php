<?php

namespace App\Menu;

use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;

class Builder
{
    public function __construct(private FactoryInterface $factory)
    {
        $this->factory = $factory;
    }

    public function createMainMenu(array $options): ItemInterface
    {
        $menu = $this->factory->createItem('mainMenu');
        $menu->addChild('Account', ['uri' => 'https://auth2.mspchallenge.info/user']);
        $menu->addChild('mspchallenge.info', [
            'uri' => 'https://www.mspchallenge.info',
            'attributes' => [
                'class' => 'dropdown'
            ],
            'linkAttributes' => [
                'class' => 'dropdown-toggle',
                'data-bs-toggle' => 'dropdown',
                'aria-expanded' => 'false',
                'id' => 'dropdownMenuLink',
            ],
            'childrenAttributes' => [
                'class' => 'dropdown-menu',
                'aria-labelledby' => 'dropdownMenuLink',
            ],
        ]);
        $menu['mspchallenge.info']->addChild('Main website', ['uri' => 'https://www.mspchallenge.info']);
        $menu['mspchallenge.info']->addChild('Community Wiki', ['uri' => 'https://community.mspchallenge.info']);
        $menu['mspchallenge.info']->addChild('Knowledge Base', ['uri' => 'https://knowledge.mspchallenge.info']);

        return $menu;
    }

    public function createSubMenu(array $options): ItemInterface
    {
        $menu = $this->factory->createItem('subMenu');
        $menu->addChild('Sessions', ['route' => 'manager']);
        $menu->addChild('Saves', ['route' => 'manager_gamesave']);
        $menu->addChild('Configurations', ['route' => 'manager_gameconfig']);
        $menu->addChild('Settings', ['route' => 'manager_setting']);
        $menu->addChild('Errors', ['route' => 'manager_errors']);

        return $menu;
    }
}
