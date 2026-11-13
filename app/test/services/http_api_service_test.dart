import 'dart:async';
import 'dart:convert';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';
import 'package:farmmantra/core/exceptions/app_exception.dart';
import 'package:farmmantra/services/api/http_api_service.dart';
import 'package:farmmantra/core/constants/api_endpoints.dart';

void main() {
  test(
    'login uses configured API and bearer token for subsequent requests',
    () async {
      final api = HttpApiService(
        client: MockClient((request) async {
          if (request.url.path.endsWith('/login')) {
            expect(request.url.toString(), '${ApiEndpoints.baseUrl}/login');
            expect(request.method, 'POST');
            expect(jsonDecode(request.body), {
              'email': 'test@example.com',
              'password': 'test-password',
            });
            return http.Response(
              jsonEncode({
                'token': 'test-token',
                'user': {'id': 1, 'name': 'Test', 'email': 'test@example.com'},
              }),
              200,
            );
          }
          expect(request.url.toString(), '${ApiEndpoints.baseUrl}/me');
          expect(request.headers['Authorization'], 'Bearer test-token');
          return http.Response(
            jsonEncode({
              'user': {'id': 1, 'name': 'Test', 'email': 'test@example.com'},
            }),
            200,
          );
        }),
      );
      final login = await api.login('test@example.com', 'test-password');
      api.setToken(login['token']);
      final user = await api.getProfile();
      expect(user.email, 'test@example.com');
    },
  );
  test('dashboard network failure does not fabricate zero totals', () async {
    final api = HttpApiService(
      client: MockClient((request) async {
        return http.Response(
          '{"message":"Unavailable"}',
          503,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    api.setToken('test-token');
    await expectLater(api.getDashboardStats(), throwsA(isA<AppException>()));
  });
  group('HttpApiService.getOrders', () {
    test('parses paginated orders response without error', () async {
      final api = HttpApiService(
        client: MockClient((request) async {
          return http.Response(
            jsonEncode(_ordersResponse),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );
      api.setToken('test-token');
      api.setUserRole('franchisePartner');

      final orders = await api.getOrders();

      expect(orders, isNotEmpty);
      expect(orders.first.id, '33');
      expect(orders.first.status, 'approved');
      expect(orders.first.createdAt, isA<DateTime>());
      expect(orders.first.updatedAt, isA<DateTime>());
      expect(orders.first.expectedDeliveryDate, isA<DateTime>());
      expect(orders.first.items.first.productName, 'Thunder 145-SE');
      expect(orders.first.items.first.categoryName, 'Insecticides');
      expect(orders.first.items.first.quantity, 8);
    });
  });

  group('HttpApiService.getPayments', () {
    test('parses paginated payments response without error', () async {
      final api = HttpApiService(
        client: MockClient((request) async {
          return http.Response(
            jsonEncode(_paymentsResponse),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );
      api.setToken('test-token');
      api.setUserRole('franchisePartner');

      final payments = await api.getPayments();

      expect(payments, isNotEmpty);
      expect(payments.first.id, '34');
      expect(payments.first.amount, 50000);
      expect(payments.first.status, 'accepted');
      expect(payments.first.franchiseName, 'Kampala Franchise');
    });
  });

  group('HttpApiService.getInventory', () {
    test('parses paginated inventory response without error', () async {
      final api = HttpApiService(
        client: MockClient((request) async {
          return http.Response(
            jsonEncode(_inventoryResponse),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );
      api.setToken('test-token');
      api.setUserRole('franchisePartner');

      final items = await api.getInventory();

      expect(items, isNotEmpty);
      expect(items.first.id, '1');
      expect(items.first.productName, 'Roundup PowerMax');
      expect(items.first.categoryName, 'Herbicides');
      expect(items.first.quantity, 9);
      expect(items.first.unitOfMeasure, 'Litres');
      expect(items.first.reorderLevel, 7);
    });
  });

  group('HttpApiService.getProducts', () {
    test(
      'parses products and populates image_url and imageUrls gallery',
      () async {
        final api = HttpApiService(
          client: MockClient((request) async {
            return http.Response(
              jsonEncode(_productsResponse),
              200,
              headers: {'content-type': 'application/json'},
            );
          }),
        );
        api.setToken('test-token');
        api.setUserRole('franchisePartner');

        final products = await api.getProducts();

        expect(products, isNotEmpty);
        expect(products.first.id, '14');
        expect(products.first.name, 'sample');
        expect(products.first.imageUrl, isNotNull);
        expect(
          products.first.imageUrl,
          contains('PJsC1onZnR0HyJFhu63feb19Rvnsq1eS3b4Z008f.jpg'),
        );
        expect(products.first.imageUrls.length, 5);
        expect(
          products.first.imageUrls.first,
          contains('PJsC1onZnR0HyJFhu63feb19Rvnsq1eS3b4Z008f.jpg'),
        );
      },
    );
  });

  group('errorMessageOf', () {
    test('surfaces AppException message', () {
      expect(
        errorMessageOf(
          const AppException(message: 'Order exceeds credit limit.'),
          'Failed to create order',
        ),
        'Order exceeds credit limit.',
      );
    });

    test('uses fallback for unknown exceptions', () {
      expect(
        errorMessageOf(Exception('boom'), 'Failed to load orders'),
        'Failed to load orders',
      );
    });

    test('maps network client errors to a friendly message', () {
      expect(
        errorMessageOf(http.ClientException('Connection refused'), 'Failed'),
        'Network error. Please check your connection and try again.',
      );
    });

    test('maps timeouts to a friendly message', () {
      expect(
        errorMessageOf(TimeoutException('timed out'), 'Failed'),
        'The request timed out. Please try again.',
      );
    });
  });
}

final _ordersResponse = {
  'data': [
    {
      'id': 33,
      'order_number': 'ORD-202607-0033',
      'franchise_id': 1,
      'ordered_by': 4,
      'status': 'approved',
      'received_at': null,
      'served_at': null,
      'completed_at': null,
      'notes': null,
      'expected_delivery_date': '2026-07-25T21:00:00.000000Z',
      'approved_by': 2,
      'approved_at': '2026-07-22T09:00:00.000000Z',
      'declined_by': null,
      'declined_at': null,
      'decline_reason': null,
      'total_amount': '1045000.00',
      'created_at': '2026-07-22T05:00:00.000000Z',
      'updated_at': '2026-07-22T11:22:50.000000Z',
      'items': [
        {
          'id': 90,
          'order_id': 33,
          'product_id': 2,
          'quantity': '8.00',
          'unit_price': '35000.00',
          'adjusted_quantity': null,
          'adjustment_notes': null,
          'original_unit_price': '35000.00',
          'subtotal': '280000.00',
          'notes': null,
          'rejection_reason': null,
          'status': 'pending',
          'created_at': '2026-07-22T11:22:50.000000Z',
          'updated_at': '2026-07-22T11:22:50.000000Z',
          'product': {
            'id': 2,
            'name': 'Thunder 145-SE',
            'sku': 'INSE-001',
            'category': {'id': 2, 'name': 'Insecticides'},
          },
        },
      ],
    },
  ],
};

final _paymentsResponse = {
  'data': [
    {
      'id': 34,
      'payment_number': 'PAY-202607-0034',
      'franchise_id': 1,
      'amount': '50000.00',
      'payment_method': 'cash',
      'transaction_reference': 'E2E-FINAL-001',
      'bank_name': null,
      'proof_of_payment_path': null,
      'status': 'accepted',
      'submitted_at': '2026-07-22T13:52:49.000000Z',
      'verified_by': 3,
      'verified_at': '2026-07-22T13:52:49.000000Z',
      'rejected_by': null,
      'rejected_at': null,
      'rejection_reason': null,
      'finance_notes': null,
      'verified_amount': '50000.00',
      'created_at': '2026-07-22T13:52:49.000000Z',
      'updated_at': '2026-07-22T13:52:49.000000Z',
      'franchise': {'id': 1, 'name': 'Kampala Franchise'},
    },
  ],
};

final _inventoryResponse = {
  'data': [
    {
      'id': 1,
      'franchise_id': 1,
      'product_id': 1,
      'quantity': '9.00',
      'reorder_level': '7.00',
      'total_value': '405000.00',
      'created_at': '2026-07-22T11:22:50.000000Z',
      'updated_at': '2026-07-22T11:29:04.000000Z',
      'product': {
        'id': 1,
        'name': 'Roundup PowerMax',
        'sku': 'HERB-001',
        'category_id': 1,
        'unit_of_measure': 'Litres',
        'packaging_details': '1L, 5L, 20L',
        'selling_price': '45000.00',
        'standard_price': '45000.00',
        'image': 'products/HERB-001.png',
        'is_active': true,
        'category': {'id': 1, 'name': 'Herbicides'},
      },
    },
  ],
};

final _productsResponse = {
  'data': [
    {
      'id': 14,
      'name': 'sample',
      'sku': '0020',
      'category_id': 1,
      'unit_of_measure': 'Kgs',
      'selling_price': '5000.00',
      'standard_price': '5000.00',
      'image': 'products/PJsC1onZnR0HyJFhu63feb19Rvnsq1eS3b4Z008f.jpg',
      'image_url':
          'http://localhost/storage/products/PJsC1onZnR0HyJFhu63feb19Rvnsq1eS3b4Z008f.jpg',
      'all_images': [
        'http://localhost/storage/products/PJsC1onZnR0HyJFhu63feb19Rvnsq1eS3b4Z008f.jpg',
        'http://localhost/storage/products/rhjjKS043xLXoXORI2vMWQEL2kxY4V5UEdh5juB8.jpg',
        'http://localhost/storage/products/4rsPGHj8FtIMg6mZIVQ0RzXXJOQIFsUjS5G4i7q0.jpg',
        'http://localhost/storage/products/s77fcc2veiQzGux8O9Hu29pE6z1pqZQCrube40A0.jpg',
        'http://localhost/storage/products/ipKQBI8SHct31RZVRqkz6BqVFDNN6Shif8kL7ewr.jpg',
      ],
      'images': [
        {
          'id': 1,
          'product_id': 14,
          'image_path': 'products/rhjjKS043xLXoXORI2vMWQEL2kxY4V5UEdh5juB8.jpg',
          'image_url':
              'http://localhost/storage/products/rhjjKS043xLXoXORI2vMWQEL2kxY4V5UEdh5juB8.jpg',
        },
      ],
      'is_active': true,
      'category': {'id': 1, 'name': 'Herbicides'},
    },
  ],
};
