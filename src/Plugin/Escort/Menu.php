<?php

namespace Drupal\escort\Plugin\Escort;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Menu\MenuActiveTrailInterface;
use Drupal\Core\Menu\MenuLinkTreeInterface;
use Drupal\Core\Menu\MenuTreeParameters;
use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides a "Menu" escort to display the links from a menu.
 *
 * @Escort(
 *   id = "menu",
 *   admin_label = @Translation("Menu"),
 *   category = @Translation("Menu"),
 * )
 */
class Menu extends EscortPluginMultipleBase implements ContainerFactoryPluginInterface {
  use EscortPluginLinkTrait;

  /**
   * {@inheritdoc}
   */
  protected $provideMultiple = TRUE;


  /**
   * {@inheritdoc}
   */
  protected $usesIcon = FALSE;

  /**
   * The menu link tree service.
   *
   * @var \Drupal\Core\Menu\MenuLinkTreeInterface
   */
  protected $menuTree;

  /**
   * The active menu trail service.
   *
   * @var \Drupal\Core\Menu\MenuActiveTrailInterface
   */
  protected $menuActiveTrail;

  /**
   * The menu storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $menuStorage;

  /**
   * Constructs a new Menu.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param array $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Menu\MenuLinkTreeInterface $menu_tree
   *   The menu tree service.
   * @param \Drupal\Core\Menu\MenuActiveTrailInterface $menu_active_trail
   *   The active menu trail service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MenuLinkTreeInterface $menu_tree, MenuActiveTrailInterface $menu_active_trail, EntityStorageInterface $menu_storage) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->menuTree = $menu_tree;
    $this->menuActiveTrail = $menu_active_trail;
    $this->menuStorage = $menu_storage;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('menu.link_tree'),
      $container->get('menu.active_trail'),
      $container->get('entity.manager')->getStorage('menu')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    return [
      'menu' => 'main',
      'level' => 1,
      'depth' => 0,
    ];
  }

  /**
   * {@inheritdoc}
   *
   * When in admin mode, we simply display the label.
   */
  public function preview() {
    return [
      '#icon' => 'fa-th-list',
      '#markup' => $this->label(TRUE) . ' ' . $this->t('Placeholder'),
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function buildItems() {
    $items = [];
    $menu_name = $this->configuration['menu'];
    $active_trail = $this->menuActiveTrail->getActiveTrailIds($menu_name);
    $parameters = new MenuTreeParameters();
    $parameters->setActiveTrail($active_trail);

    $level = $this->configuration['level'];
    $depth = $this->configuration['depth'];
    $parameters->setMinDepth($level);
    // When the depth is configured to zero, there is no depth limit. When depth
    // is non-zero, it indicates the number of levels that must be displayed.
    // Hence this is a relative depth that we must convert to an actual
    // (absolute) depth, that may never exceed the maximum depth.
    if ($depth > 0) {
      $parameters->setMaxDepth(min($level + $depth - 1, $this->menuTree->maxDepth()));
    }

    $tree = $this->menuTree->load($menu_name, $parameters);
    $manipulators = array(
      array('callable' => 'menu.default_tree_manipulators:checkAccess'),
      array('callable' => 'menu.default_tree_manipulators:generateIndexAndSort'),
    );
    $tree = $this->menuTree->transform($tree, $manipulators);

    $render = $this->menuTree->build($tree);
    $items = $this->buildFlattenedItems($render['#items'], $this->flattenTree($tree));
    $items['#cache'] = $render['#cache'];

    return $items;
  }

  /**
   * Prepare tab for rendering.
   *
   * @param array $items
   *   The renderable menu.
   * @param array $tree
   *   The menu tree.
   *
   * @return array
   *   An array of tabs.
   */
  protected function buildFlattenedItems($items, $tree, $build = []) {
    foreach ($items as $id => $item) {
      $build_item = [];
      $title = $item['title'];
      $url = $item['url'];
      $options = $url->getOptions();
      $build_item = $this->buildLink($title, $url);
      // Set depth class.
      $build_item['#attributes']['class'][] = 'escort-depth-' . $tree[$id]->depth;
      // Set active class.
      if ($item['in_active_trail']) {
        $build_item['#attributes']['class'][] = 'is-active';
      }
      $build[] = $build_item;
      if ($item['below']) {
        $build = $this->buildFlattenedItems($item['below'], $tree, $build);
      }
    }
    return $build;
  }

  /**
   * Loop through tree and return each item on the same level.
   *
   * @param array $tree
   *   An array of menu items.
   *
   * @return array
   *   The items.
   */
  protected function flattenTree($tree, $items = []) {
    foreach ($tree as $id => $item) {
      // $items[] = $this->buildItem($item);
      $items[$item->link->getPluginId()] = $item;
      if ($item->hasChildren) {
        $items = $this->flattenTree($item->subtree, $items);
      }
    }
    return $items;
  }

  /**
   * {@inheritdoc}
   */
  public function escortForm($form, FormStateInterface $form_state) {
    $config = $this->configuration;
    $defaults = $this->defaultConfiguration();
    $menus = [];
    foreach ($this->menuStorage->loadMultiple() as $menu => $entity) {
      $menus[$entity->id()] = $entity->label();
    }

    $form['menu'] = [
      '#type' => 'select',
      '#title' => $this->t('Menu'),
      '#options' => $menus,
      '#default_value' => $config['menu'],
    ];

    $form['menu_levels'] = array(
      '#type' => 'details',
      '#title' => $this->t('Menu levels'),
      // Open if not set to defaults.
      '#open' => $defaults['level'] !== $config['level'] || $defaults['depth'] !== $config['depth'],
      '#process' => [[get_class(), 'processMenuLevelParents']],
    );

    $options = range(0, $this->menuTree->maxDepth());
    unset($options[0]);

    $form['menu_levels']['level'] = array(
      '#type' => 'select',
      '#title' => $this->t('Initial menu level'),
      '#default_value' => $config['level'],
      '#options' => $options,
      '#description' => $this->t('The menu will only be visible if the menu item for the current page is at or below the selected starting level. Select level 1 to always keep this menu visible.'),
      '#required' => TRUE,
    );

    $options[0] = $this->t('Unlimited');

    $form['menu_levels']['depth'] = array(
      '#type' => 'select',
      '#title' => $this->t('Maximum number of menu levels to display'),
      '#default_value' => $config['depth'],
      '#options' => $options,
      '#description' => $this->t('The maximum number of menu levels to show, starting from the initial menu level. For example: with an initial level 2 and a maximum number of 3, menu levels 2, 3 and 4 can be displayed.'),
      '#required' => TRUE,
    );

    return $form;
  }

  /**
   * Form API callback: Processes the menu_levels field element.
   *
   * Adjusts the #parents of menu_levels to save its children at the top level.
   */
  public static function processMenuLevelParents(&$element, FormStateInterface $form_state, &$complete_form) {
    array_pop($element['#parents']);
    return $element;
  }

  /**
   * {@inheritdoc}
   */
  public function escortSubmit($form, FormStateInterface $form_state) {
    $this->configuration['menu'] = $form_state->getValue('menu');
    $this->configuration['level'] = $form_state->getValue('level');
    $this->configuration['depth'] = $form_state->getValue('depth');
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    // Even when the menu block renders to the empty string for a user, we want
    // the cache tag for this menu to be set: whenever the menu is changed, this
    // menu block must also be re-rendered for that user, because maybe a menu
    // link that is accessible for that user has been added.
    $cache_tags = parent::getCacheTags();
    $cache_tags[] = 'config:system.menu.' . $this->configuration['menu'];
    return $cache_tags;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // ::build() uses MenuLinkTreeInterface::getCurrentRouteMenuTreeParameters()
    // to generate menu tree parameters, and those take the active menu trail
    // into account. Therefore, we must vary the rendered menu by the active
    // trail of the rendered menu.
    // Additional cache contexts, e.g. those that determine link text or
    // accessibility of a menu, will be bubbled automatically.
    $menu_name = $this->configuration['menu'];
    return Cache::mergeContexts(parent::getCacheContexts(), ['route.menu_active_trails:' . $menu_name]);
  }

}
