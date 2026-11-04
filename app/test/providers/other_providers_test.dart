import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/providers/customer_provider.dart';
import 'package:farmmantra/providers/inventory_provider.dart';
import 'package:farmmantra/providers/notification_provider.dart';
import 'package:farmmantra/services/api/mock_api_service.dart';

void main() {
  late MockApiService apiService;

  setUp(() async {
    apiService = MockApiService();
    await apiService.initialize();
  });

  group('CustomerProvider', () {
    late CustomerProvider provider;

    setUp(() {
      provider = CustomerProvider(apiService: apiService);
    });

    test('initial state', () {
      expect(provider.customers, isEmpty);
      expect(provider.isLoading, false);
      expect(provider.isEmpty, true);
    });

    test('loadCustomers populates customers', () async {
      await provider.loadCustomers();
      expect(provider.customers, isNotEmpty);
      expect(provider.customers.length, 2);
    });

    test('createCustomer adds customer to list', () async {
      await provider.loadCustomers();
      final count = provider.customers.length;

      final success = await provider.createCustomer({
        'name': 'New Farmer',
        'phone': '+256700000111',
      });

      expect(success, true);
      expect(provider.customers.length, count + 1);
    });
  });

  group('InventoryProvider', () {
    late InventoryProvider provider;

    setUp(() {
      provider = InventoryProvider(apiService: apiService);
    });

    test('initial state', () {
      expect(provider.items, isEmpty);
      expect(provider.movements, isEmpty);
      expect(provider.isLoading, false);
      expect(provider.totalValue, 0);
    });

    test('loadInventory populates items', () async {
      await provider.loadInventory();
      expect(provider.items, isNotEmpty);
      expect(provider.items.length, 5);
      expect(provider.isLoading, false);
    });

    test('totalValue sums item values', () async {
      await provider.loadInventory();
      final expected = provider.items.fold(0.0, (s, i) => s + i.totalValue);
      expect(provider.totalValue, expected);
    });

    test('lowStockItems filters correctly', () async {
      await provider.loadInventory();
      expect(
        provider.lowStockItems.every((i) => i.isLowStock && !i.isOutOfStock),
        true,
      );
    });

    test('outOfStockItems filters correctly', () async {
      await provider.loadInventory();
      expect(provider.outOfStockItems.every((i) => i.isOutOfStock), true);
    });

    test('loadMovements populates movements', () async {
      await provider.loadMovements();
      expect(provider.movements, isNotEmpty);
    });
  });

  group('NotificationProvider', () {
    late NotificationProvider provider;

    setUp(() async {
      await apiService.login('test@test.com', 'password123');
      provider = NotificationProvider(apiService: apiService);
    });

    test('initial state', () {
      expect(provider.notifications, isEmpty);
      expect(provider.unreadCount, 0);
      expect(provider.isLoading, false);
      expect(provider.isEmpty, true);
    });

    test('loadNotifications populates list', () async {
      await provider.loadNotifications();
      expect(provider.notifications, isNotEmpty);
      expect(provider.notifications.length, 4);
    });

    test('unreadCount tracks unread notifications', () async {
      await provider.loadNotifications();
      expect(provider.unreadCount, greaterThan(0));
    });

    test('markAsRead decrements unread count', () async {
      await provider.loadNotifications();
      final initialCount = provider.unreadCount;
      final unreadNotification = provider.notifications.firstWhere(
        (n) => !n.isRead,
      );

      await provider.markAsRead(unreadNotification.id);
      expect(provider.unreadCount, initialCount - 1);
    });

    test('markAllAsRead sets unreadCount to 0', () async {
      await provider.loadNotifications();
      expect(provider.unreadCount, greaterThan(0));

      await provider.markAllAsRead();
      expect(provider.unreadCount, 0);
    });

    test('refreshUnreadCount updates count', () async {
      await provider.loadNotifications();
      await provider.refreshUnreadCount();
      expect(provider.unreadCount, greaterThanOrEqualTo(0));
    });
  });
}
