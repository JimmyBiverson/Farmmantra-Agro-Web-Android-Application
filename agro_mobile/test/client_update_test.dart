import 'dart:convert';

import 'package:agro_app/core/enums/user_role.dart';
import 'package:agro_app/models/customer.dart';
import 'package:agro_app/models/order.dart';
import 'package:agro_app/models/product.dart';
import 'package:agro_app/models/user.dart';
import 'package:agro_app/services/api/http_api_service.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:http/http.dart' as http;
import 'package:http/testing.dart';

/// Tests for the October 2026 client update (warehouse role, price tiers,
/// delivery acceptance with terms, dispatch details, statements).
void main() {
  group('Roles', () {
    test('warehouse manages orders, sales staff only monitor', () {
      expect(UserRole.warehouse.canManageOrders, isTrue);
      expect(UserRole.systemAdministrator.canManageOrders, isTrue);
      expect(UserRole.farmmantraStaff.canManageOrders, isFalse);
      expect(UserRole.franchisePartner.canManageOrders, isFalse);
      expect(UserRole.warehouse.usesStaffArea, isTrue);
    });

    test('login maps the Laravel "Warehouse" role and terms flag', () async {
      final api = HttpApiService(
        client: MockClient((request) async {
          return http.Response(
            jsonEncode({
              'token': 't',
              'user': {
                'id': 9,
                'name': 'Warehouse Officer',
                'email': 'wh@x.ug',
                'role': 'Warehouse',
                'needs_terms_acceptance': false,
              },
            }),
            200,
            headers: {'content-type': 'application/json'},
          );
        }),
      );
      final result = await api.login('wh@x.ug', 'secret');
      final user = User.fromJson(result['user'] as Map<String, dynamic>);
      expect(user.role, UserRole.warehouse);
      expect(user.needsTermsAcceptance, isFalse);
    });

    test('franchise needing terms acceptance is flagged', () {
      final user = User.fromJson({
        'id': '1',
        'name': 'F',
        'email': 'f@x.ug',
        'phone': '',
        'role': 'franchisePartner',
        'needs_terms_acceptance': true,
      });
      expect(user.needsTermsAcceptance, isTrue);
      expect(user.copyWith(needsTermsAcceptance: false).needsTermsAcceptance, isFalse);
    });
  });

  group('Order (dispatch, payment terms, receipt lines)', () {
    Map<String, dynamic> base(Map<String, dynamic> extra) => {
      'id': 5,
      'order_number': 'ORD-1',
      'franchise_id': 1,
      'status': 'approved',
      'delivery_status': 'pending',
      'total_amount': '500000.00',
      'items': [],
      ...extra,
    };

    test('credit orders can be dispatched without payment, cash orders cannot', () {
      final credit = Order.fromJson(base({'payment_terms': 'credit'}));
      final cash = Order.fromJson(base({'payment_terms': 'cash'}));
      expect(credit.canBeDispatched, isTrue);
      expect(cash.canBeDispatched, isFalse);
      final paidCash = Order.fromJson(base({'payment_terms': 'cash', 'delivery_status': 'ready_for_delivery'}));
      expect(paidCash.canBeDispatched, isTrue);
    });

    test('parses dispatch details, signatures, amount paid and receipt lines', () {
      final order = Order.fromJson(base({
        'delivery_status': 'out_for_delivery',
        'amount_paid': '100000.00',
        'dispatch_means': 'bus',
        'dispatch_vehicle': 'Gateway Bus',
        'approval_signature': 'FMS-261027-ABC123',
        'stock_receipt': {
          'items': [
            {'id': 77, 'ordered_quantity': '10.00', 'received_quantity': '0.00', 'product': {'name': 'ABC 1Ltr'}},
          ],
        },
      }));
      expect(order.dispatchMeansLabel, 'Bus');
      expect(order.dispatchVehicle, 'Gateway Bus');
      expect(order.approvalSignature, 'FMS-261027-ABC123');
      expect(order.amountPaid, 100000);
      expect(order.balanceDue, 400000);
      expect(order.awaitingFranchiseAcceptance, isTrue);
      expect(order.receiptLines.single.id, '77');
      expect(order.receiptLines.single.dispatchedQuantity, 10);
      expect(order.receiptLines.single.productName, 'ABC 1Ltr');
    });

    test('confirmed orders are no longer awaiting acceptance', () {
      final order = Order.fromJson(base({'status': 'delivered', 'delivery_status': 'confirmed'}));
      expect(order.awaitingFranchiseAcceptance, isFalse);
    });
  });

  test('product exposes admin-fixed retail and farmer prices', () {
    final p = Product.fromJson({
      'id': 1,
      'name': 'ABC 1Ltr',
      'standard_price': '10000.00',
      'retail_price': 10500,
      'farmer_unit_price': 11400,
    });
    expect(p.standardPrice, 10000);
    expect(p.sellingPriceFor('retailer'), 10500);
    expect(p.sellingPriceFor('farmer'), 11400);
  });

  test('customer carries its type', () {
    final c = Customer.fromJson({'id': 1, 'name': 'Shop', 'phone': '07', 'customer_type': 'retailer'});
    expect(c.isRetailer, isTrue);
    expect(c.typeLabel, 'Retailer');
    expect(Customer.fromJson({'id': 2, 'name': 'Ann', 'phone': '07'}).customerType, 'farmer');
  });

  test('accepting a delivery posts accept_terms and per-line quantities', () async {
    late Map<String, dynamic> sent;
    late String path;
    final api = HttpApiService(
      client: MockClient((request) async {
        path = request.url.path;
        sent = jsonDecode(request.body) as Map<String, dynamic>;
        return http.Response(
          jsonEncode({
            'data': {'id': 5, 'status': 'delivered', 'delivery_status': 'confirmed', 'total_amount': 1, 'items': []},
          }),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    api.setToken('t');
    api.setUserRole('franchisePartner');
    final order = await api.confirmDelivery('5', notes: '2 damaged', receivedQuantities: {'77': 8});
    expect(path, endsWith('/franchise/orders/5/confirm-delivery'));
    expect(sent['accept_terms'], isTrue);
    expect(sent['notes'], '2 damaged');
    expect((sent['items'] as List).single, {'stock_receipt_item_id': '77', 'received_quantity': 8.0});
    expect(order.deliveryStatus, 'confirmed');
  });

  test('dispatch sends means, vehicle and remarks', () async {
    late Map<String, dynamic> sent;
    final api = HttpApiService(
      client: MockClient((request) async {
        sent = jsonDecode(request.body) as Map<String, dynamic>;
        return http.Response(
          jsonEncode({'data': {'id': 5, 'status': 'approved', 'delivery_status': 'out_for_delivery', 'total_amount': 1, 'items': []}}),
          200,
          headers: {'content-type': 'application/json'},
        );
      }),
    );
    api.setToken('t');
    api.setUserRole('warehouse');
    await api.dispatchOrder('5', means: 'bus', vehicle: 'Gateway', remarks: 'Parcel office');
    expect(sent, {'dispatch_means': 'bus', 'dispatch_vehicle': 'Gateway', 'dispatch_remarks': 'Parcel office'});
  });
}
