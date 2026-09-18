<?php

namespace App\Menu;

use App\Entity\ServerManager\User;
use Knp\Menu\FactoryInterface;
use Knp\Menu\ItemInterface;
use Symfony\Bundle\SecurityBundle\Security;

class Builder
{
    public function __construct(
        private readonly FactoryInterface $factory,
        private readonly Security $security
    ) {
    }

    public function createMainMenu(array $options): ItemInterface
    {
        $menu = $this->factory->createItem('mainMenu');
        $user = $this->security->getUser();
        if ($user instanceof User) {
            $menu->addChild('Logged in as ' . $user->getUsername(), [
                'attributes' => ['class' => 'menu-user-indicator'],
            ]);
            $menu->addChild('Logout', ['route' => 'manager_logout']);
        }
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
        $menu->addChild('Database', ['route' => 'manager_database']);
        $menu->addChild('Saves', ['route' => 'manager_gamesave']);
        $menu->addChild('Configurations', ['route' => 'manager_gameconfig']);
        $menu->addChild('Settings', ['route' => 'manager_setting']);
        $menu->addChild('Errors', ['route' => 'manager_errors']);

        return $menu;
    }
}
