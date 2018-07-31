<?php

namespace Drupal\commerce_checkout_api\Plugin\rest\resource;

use Drupal\commerce\PurchasableEntityInterface;
use Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface;
use Drupal\commerce_store\CurrentStoreInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\rest\Plugin\ResourceBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

abstract class CheckoutResourceBase extends ResourceBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The order item store.
   *
   * @var \Drupal\commerce_order\OrderItemStorageInterface
   */
  protected $orderItemStorage;

  protected $orderStorage;

  /**
   * The chain order type resolver.
   *
   * @var \Drupal\commerce_order\Resolver\ChainOrderTypeResolverInterface
   */
  protected $chainOrderTypeResolver;

  /**
   * The current store.
   *
   * @var \Drupal\commerce_store\CurrentStoreInterface
   */
  protected $currentStore;

  protected $currentUser;

  public function __construct(array $configuration, $plugin_id, $plugin_definition, array $serializer_formats, LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, ChainOrderTypeResolverInterface $chain_order_type_resolver, CurrentStoreInterface $current_store, AccountInterface $account) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $serializer_formats, $logger);
    $this->entityTypeManager = $entity_type_manager;
    $this->orderItemStorage = $entity_type_manager->getStorage('commerce_order_item');
    $this->orderStorage = $entity_type_manager->getStorage('commerce_order');
    $this->chainOrderTypeResolver = $chain_order_type_resolver;
    $this->currentStore = $current_store;
    $this->currentUser = $account;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->getParameter('serializer.formats'),
      $container->get('logger.factory')->get('rest'),
      $container->get('entity_type.manager'),
      $container->get('commerce_order.chain_order_type_resolver'),
      $container->get('commerce_store.current_store'),
      $container->get('current_user')
    );
  }

  /**
   * {@inheritdoc}
   *
   * There are no permissions needed.
   *
   * @see ::getBaseRouteRequirements()
   */
  public function permissions() {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  protected function getBaseRouteRequirements($method) {
    $requirements = parent::getBaseRouteRequirements($method);
    $requirements['_checkout_api'] = 'TRUE';
    return $requirements;
  }

  /**
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   * @param $body
   * @param \Symfony\Component\HttpFoundation\Request $request
   *
   * @throws \Drupal\Component\Plugin\Exception\InvalidPluginDefinitionException
   * @throws \Drupal\Component\Plugin\Exception\PluginNotFoundException
   *
   *
   * @todo We need to ensure that only one order would be created.
   *
   * To do that, we first need to resolve all of the possible stores and order
   * types. If there is any mismatch in stores or order types, the process
   * must fail.
   *
   * The end of this should result in a single order.
   *
   * @todo Support non-commerce_product architecture
   * @todo Support combining order items, copying parts of cart.
   */
  protected function initiateOrder($body, Request $request) {
    // Do an initial validation of the payload before any processing.
    if (empty($body['purchasedEntities'])) {
      throw new UnprocessableEntityHttpException('You must provide an array of purchased entities.');
    }

    $entity_repository = \Drupal::getContainer()->get('entity.repository');

    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = NULL;
    foreach ($body['purchasedEntities'] as $purchased_entity_data) {
      // @todo: Determine from the purchased entity data.
      $storage = $this->entityTypeManager->getStorage('commerce_product_variation');
      $purchased_entity = $entity_repository->loadEntityByUuid('commerce_product_variation', $purchased_entity_data['id']);
      if (!$purchased_entity || !$purchased_entity instanceof PurchasableEntityInterface) {
        continue;
      }

      $order_item = $this->orderItemStorage->createFromPurchasableEntity($purchased_entity, [
        'quantity' => 1,
      ]);

      $store = $this->selectStore($purchased_entity);
      $order_type_id = $this->chainOrderTypeResolver->resolve($order_item);
      // The first order item dictates the order type and store.
      if (!$order) {
        $order = $this->orderStorage->create([
          'type' => $order_type_id,
          'store_id' => $store->id(),
          'uid' => $this->currentUser->id(),
        ]);
      }
      else {
        if ($store->id() != $order->getStoreId()) {
          throw new UnprocessableEntityHttpException('Stores mismatch');
        }
        if ($order_type_id != $order->bundle()) {
          throw new UnprocessableEntityHttpException('Order type mismatch');
        }
      }

      $order->get('order_items')->appendItem($order_item);
    }
    if ($order) {
      $order->recalculateTotalPrice();
    }

    return $order;
  }

  /**
   * Selects the store for the given purchasable entity.
   *
   * If the entity is sold from one store, then that store is selected.
   * If the entity is sold from multiple stores, and the current store is
   * one of them, then that store is selected.
   *
   * @param \Drupal\commerce\PurchasableEntityInterface $entity
   *   The entity being added to cart.
   *
   * @throws \Exception
   *   When the entity can't be purchased from the current store.
   *
   * @return \Drupal\commerce_store\Entity\StoreInterface
   *   The selected store.
   */
  protected function selectStore(PurchasableEntityInterface $entity) {
    $stores = $entity->getStores();
    if (count($stores) === 1) {
      $store = reset($stores);
    }
    elseif (count($stores) === 0) {
      // Malformed entity.
      throw new \Exception('The given entity is not assigned to any store.');
    }
    else {
      $store = $this->currentStore->getStore();
      if (!in_array($store, $stores)) {
        // Indicates that the site listings are not filtered properly.
        throw new \Exception("The given entity can't be purchased from the current store.");
      }
    }

    return $store;
  }

}
