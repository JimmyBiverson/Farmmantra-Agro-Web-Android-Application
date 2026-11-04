import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/models/order.dart';
import 'package:farmmantra/core/enums/app_enums.dart';

void main() {
  group('Order', () {
    test('fromJson parses order correctly', () {
      final json = {
        'id': 'ORD-1001',
        'order_number': 'ORD-1001',
        'franchise_id': 'F001',
        'franchise_name': 'Kampala',
        'items': [
          {
            'id': '1',
            'product_id': 'P1',
            'product_name': 'Roundup',
            'category_name': 'Herbicides',
            'quantity': 10,
            'unit_price': 42000,
            'subtotal': 420000,
          },
        ],
        'total_amount': 420000,
        'status': 'pending',
        'created_at': '2024-01-15T10:00:00.000Z',
        'updated_at': '2024-01-15T10:00:00.000Z',
      };
      final order = Order.fromJson(json);

      expect(order.id, 'ORD-1001');
      expect(order.franchiseName, 'Kampala');
      expect(order.items.length, 1);
      expect(order.totalAmount, 420000);
      expect(order.status, 'pending');
      expect(order.createdAt, isA<DateTime>());
      expect(order.updatedAt, isA<DateTime>());
    });

    test('fromJson handles missing fields gracefully', () {
      final order = Order.fromJson({});
      expect(order.id, '');
      expect(order.items, isEmpty);
      expect(order.totalAmount, 0);
      expect(order.status, 'pending');
      expect(order.createdAt, isA<DateTime>());
      expect(order.updatedAt, isA<DateTime>());
    });

    test('fromJson parses expectedDeliveryDate as DateTime', () {
      final json = {
        'id': '1',
        'franchise_id': 'F1',
        'franchise_name': 'Test',
        'total_amount': 0,
        'status': 'approved',
        'created_at': '2024-01-01T00:00:00.000Z',
        'updated_at': '2024-01-01T00:00:00.000Z',
        'expected_delivery_date': '2024-01-10T00:00:00.000Z',
      };
      final order = Order.fromJson(json);
      expect(order.expectedDeliveryDate, isA<DateTime>());
    });

    test('fromJson parses deliveredAt as DateTime', () {
      final json = {
        'id': '1',
        'franchise_id': 'F1',
        'franchise_name': 'Test',
        'total_amount': 0,
        'status': 'delivered',
        'created_at': '2024-01-01T00:00:00.000Z',
        'updated_at': '2024-01-01T00:00:00.000Z',
        'delivered_at': '2024-01-08T00:00:00.000Z',
      };
      final order = Order.fromJson(json);
      expect(order.deliveredAt, isA<DateTime>());
    });

    test('finance approval accepts boolean and nested payment responses', () {
      final booleanOrder = Order.fromJson({
        'status': 'approved',
        'payment_accepted': true,
      });
      final nestedOrder = Order.fromJson({
        'status': 'approved',
        'payment': {'status': 'accepted'},
      });

      expect(booleanOrder.financeApproved, isTrue);
      expect(nestedOrder.financeApproved, isTrue);
    });

    test('delivery status completes an order even when status is stale', () {
      final order = Order.fromJson({
        'status': 'approved',
        'delivery_status': 'delivered',
      });

      expect(order.statusEnum, OrderStatus.delivered);
      expect(order.isFullyCompleted, isTrue);
      expect(order.financeApproved, isTrue);
    });

    test('completed delivery marks payment and dispatch complete', () {
      final order = Order.fromJson({
        'status': 'approved',
        'delivery_status': 'delivered',
      });

      expect(order.isPaymentComplete, isTrue);
      expect(order.isDispatchComplete, isTrue);
    });

    test('statusEnum maps all statuses correctly', () {
      expect(
        const Order(
          id: '',
          franchiseId: '',
          franchiseName: '',
          items: [],
          totalAmount: 0,
          status: 'pending',
          createdAt: null,
          updatedAt: null,
        ).statusEnum,
        OrderStatus.pending,
      );
      expect(
        const Order(
          id: '',
          franchiseId: '',
          franchiseName: '',
          items: [],
          totalAmount: 0,
          status: 'approved',
          createdAt: null,
          updatedAt: null,
        ).statusEnum,
        OrderStatus.approved,
      );
      expect(
        const Order(
          id: '',
          franchiseId: '',
          franchiseName: '',
          items: [],
          totalAmount: 0,
          status: 'declined',
          createdAt: null,
          updatedAt: null,
        ).statusEnum,
        OrderStatus.declined,
      );
      expect(
        const Order(
          id: '',
          franchiseId: '',
          franchiseName: '',
          items: [],
          totalAmount: 0,
          status: 'adjusted',
          createdAt: null,
          updatedAt: null,
        ).statusEnum,
        OrderStatus.adjusted,
      );
      expect(
        const Order(
          id: '',
          franchiseId: '',
          franchiseName: '',
          items: [],
          totalAmount: 0,
          status: 'delivered',
          createdAt: null,
          updatedAt: null,
        ).statusEnum,
        OrderStatus.delivered,
      );
      expect(
        const Order(
          id: '',
          franchiseId: '',
          franchiseName: '',
          items: [],
          totalAmount: 0,
          status: 'cancelled',
          createdAt: null,
          updatedAt: null,
        ).statusEnum,
        OrderStatus.cancelled,
      );
    });

    test('statusEnum defaults to pending for unknown status', () {
      final order = const Order(
        id: '',
        franchiseId: '',
        franchiseName: '',
        items: [],
        totalAmount: 0,
        status: 'unknown',
        createdAt: null,
        updatedAt: null,
      );
      expect(order.statusEnum, OrderStatus.pending);
    });

    test('totalItems sums item quantities', () {
      final order = Order(
        id: '1',
        franchiseId: 'F1',
        franchiseName: 'Test',
        items: const [
          OrderItem(
            id: '1',
            productId: 'P1',
            productName: 'A',
            categoryName: 'C1',
            quantity: 5,
            unitPrice: 100,
            totalPrice: 500,
          ),
          OrderItem(
            id: '2',
            productId: 'P2',
            productName: 'B',
            categoryName: 'C2',
            quantity: 3,
            unitPrice: 200,
            totalPrice: 600,
          ),
        ],
        totalAmount: 1100,
        status: 'pending',
        createdAt: DateTime.now(),
        updatedAt: DateTime.now(),
      );
      expect(order.totalItems, 8);
    });

    test('displayOrderNumber uses orderNumber when available', () {
      final order = const Order(
        id: 'ID-99',
        orderNumber: 'ORD-1001',
        franchiseId: 'F1',
        franchiseName: 'Test',
        items: [],
        totalAmount: 0,
        status: 'pending',
        createdAt: null,
        updatedAt: null,
      );
      expect(order.displayOrderNumber, 'ORD-1001');
    });

    test('displayOrderNumber falls back to id', () {
      final order = const Order(
        id: 'ID-99',
        franchiseId: 'F1',
        franchiseName: 'Test',
        items: [],
        totalAmount: 0,
        status: 'pending',
        createdAt: null,
        updatedAt: null,
      );
      expect(order.displayOrderNumber, 'ID-99');
    });
  });

  group('OrderItem', () {
    test('fromJson parses correctly', () {
      final json = {
        'id': '1',
        'product_id': 'P1',
        'product_name': 'Roundup',
        'category_name': 'Herbicides',
        'quantity': 10,
        'unit_price': 42000,
        'subtotal': 420000,
      };
      final item = OrderItem.fromJson(json);
      expect(item.quantity, 10);
      expect(item.unitPrice, 42000);
      expect(item.totalPrice, 420000);
    });

    test('fromJson falls back to total_price when subtotal missing', () {
      final json = {
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 5,
        'unit_price': 100,
        'total_price': 500,
      };
      final item = OrderItem.fromJson(json);
      expect(item.totalPrice, 500);
    });

    test('effectiveQuantity returns adjustedQuantity when present', () {
      const item = OrderItem(
        id: '1',
        productId: 'P1',
        productName: 'A',
        categoryName: 'C',
        quantity: 10,
        unitPrice: 100,
        totalPrice: 1000,
        adjustedQuantity: 8,
      );
      expect(item.effectiveQuantity, 8);
    });

    test('effectiveQuantity falls back to quantity', () {
      const item = OrderItem(
        id: '1',
        productId: 'P1',
        productName: 'A',
        categoryName: 'C',
        quantity: 10,
        unitPrice: 100,
        totalPrice: 1000,
      );
      expect(item.effectiveQuantity, 10);
    });
  });
}
