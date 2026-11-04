import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/providers/order_provider.dart';
import 'package:farmmantra/services/api/mock_api_service.dart';

void main() {
  late MockApiService apiService;
  late OrderProvider provider;

  setUp(() async {
    apiService = MockApiService();
    await apiService.initialize();
    provider = OrderProvider(apiService: apiService);
  });

  group('OrderProvider', () {
    test('initial state', () {
      expect(provider.orders, isEmpty);
      expect(provider.selectedOrder, isNull);
      expect(provider.isLoading, false);
      expect(provider.cartItems, isEmpty);
      expect(provider.isEmpty, true);
    });

    test('loadOrders populates orders', () async {
      await provider.loadOrders();
      expect(provider.orders, isNotEmpty);
      expect(provider.orders.length, 3);
      expect(provider.isLoading, false);
    });

    test('loadOrders filters by status', () async {
      await provider.loadOrders(status: 'pending');
      expect(provider.orders.every((o) => o.status == 'pending'), true);
    });

    test('loadOrder sets selectedOrder', () async {
      await provider.loadOrders();
      final orderId = provider.orders.first.id;
      await provider.loadOrder(orderId);
      expect(provider.selectedOrder, isNotNull);
      expect(provider.selectedOrder!.id, orderId);
    });

    test('createOrder adds order to list', () async {
      await provider.loadOrders();
      final count = provider.orders.length;

      final success = await provider.createOrder({
        'items': [
          {
            'product_id': '1',
            'quantity': 5,
            'unit_price': 42000,
            'total_price': 210000,
          },
        ],
        'total_amount': 210000,
      });

      expect(success, true);
      expect(provider.orders.length, count + 1);
    });

    test('createOrder clears cart on success', () async {
      provider.addToCart('1', 5);
      expect(provider.cartItems, isNotEmpty);

      await provider.createOrder({'items': [], 'total_amount': 0});
      expect(provider.cartItems, isEmpty);
    });

    test('cart methods work correctly', () {
      provider.addToCart('P1', 5);
      expect(provider.cartItems['P1'], 5);

      provider.addToCart('P1', 3);
      expect(provider.cartItems['P1'], 8);

      provider.updateCartQuantity('P1', 2);
      expect(provider.cartItems['P1'], 2);

      provider.removeFromCart('P1');
      expect(provider.cartItems, isEmpty);
    });

    test('updateCartQuantity removes when qty <= 0', () {
      provider.addToCart('P1', 5);
      provider.updateCartQuantity('P1', 0);
      expect(provider.cartItems, isEmpty);
    });

    test('getCartItems returns correct format', () {
      provider.addToCart('P1', 5);
      provider.addToCart('P2', 3);

      final items = provider.getCartItems();
      expect(items.length, 2);
      expect(items.first.containsKey('product_id'), true);
      expect(items.first.containsKey('quantity'), true);
    });

    test('clearCart empties the cart', () {
      provider.addToCart('P1', 5);
      provider.addToCart('P2', 3);
      provider.clearCart();
      expect(provider.cartItems, isEmpty);
    });
  });
}
