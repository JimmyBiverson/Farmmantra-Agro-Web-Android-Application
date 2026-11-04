import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/models/inventory.dart';
import 'package:farmmantra/core/enums/app_enums.dart';

void main() {
  group('InventoryItem', () {
    test('fromJson parses correctly', () {
      final json = {
        'id': 'inv-1',
        'product_id': 'P1',
        'product_name': 'Roundup',
        'category_name': 'Herbicides',
        'quantity': 45,
        'unit_of_measure': 'Litres',
        'unit_cost': 42000,
        'total_value': 1890000,
        'reorder_level': 10,
        'alert_level': 'normal',
        'updated_at': '2024-01-15',
      };
      final item = InventoryItem.fromJson(json);
      expect(item.productName, 'Roundup');
      expect(item.quantity, 45);
      expect(item.alertLevel, InventoryAlertLevel.normal);
    });

    test('_parseAlertLevel from API level string', () {
      final normal = InventoryItem.fromJson({
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 100,
        'unit_of_measure': 'Kg',
        'reorder_level': 10,
        'alert_level': 'normal',
      });
      expect(normal.alertLevel, InventoryAlertLevel.normal);

      final low = InventoryItem.fromJson({
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 8,
        'unit_of_measure': 'Kg',
        'reorder_level': 10,
        'alert_level': 'low',
      });
      expect(low.alertLevel, InventoryAlertLevel.low);

      final critical = InventoryItem.fromJson({
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 2,
        'unit_of_measure': 'Kg',
        'reorder_level': 10,
        'alert_level': 'critical',
      });
      expect(critical.alertLevel, InventoryAlertLevel.critical);

      final outOfStock = InventoryItem.fromJson({
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 0,
        'unit_of_measure': 'Kg',
        'reorder_level': 10,
        'alert_level': 'out_of_stock',
      });
      expect(outOfStock.alertLevel, InventoryAlertLevel.outOfStock);
    });

    test('_parseAlertLevel auto-detects from quantity', () {
      final outOfStock = InventoryItem.fromJson({
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 0,
        'unit_of_measure': 'Kg',
        'reorder_level': 10,
      });
      expect(outOfStock.alertLevel, InventoryAlertLevel.outOfStock);

      final critical = InventoryItem.fromJson({
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 3,
        'unit_of_measure': 'Kg',
        'reorder_level': 10,
      });
      expect(critical.alertLevel, InventoryAlertLevel.critical);

      final low = InventoryItem.fromJson({
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 8,
        'unit_of_measure': 'Kg',
        'reorder_level': 10,
      });
      expect(low.alertLevel, InventoryAlertLevel.low);

      final normal = InventoryItem.fromJson({
        'id': '1',
        'product_id': 'P1',
        'product_name': 'A',
        'category_name': 'C',
        'quantity': 50,
        'unit_of_measure': 'Kg',
        'reorder_level': 10,
      });
      expect(normal.alertLevel, InventoryAlertLevel.normal);
    });

    test('isLowStock returns true when quantity <= reorderLevel and > 0', () {
      const item = InventoryItem(
        id: '1',
        productId: 'P1',
        productName: 'A',
        categoryName: 'C',
        quantity: 8,
        unitOfMeasure: 'Kg',
        unitCost: 100,
        totalValue: 800,
        reorderLevel: 10,
        alertLevel: InventoryAlertLevel.low,
        lastUpdated: '',
      );
      expect(item.isLowStock, true);
    });

    test('isOutOfStock returns true when quantity is 0', () {
      const item = InventoryItem(
        id: '1',
        productId: 'P1',
        productName: 'A',
        categoryName: 'C',
        quantity: 0,
        unitOfMeasure: 'Kg',
        unitCost: 100,
        totalValue: 0,
        reorderLevel: 10,
        alertLevel: InventoryAlertLevel.outOfStock,
        lastUpdated: '',
      );
      expect(item.isOutOfStock, true);
    });
  });

  group('InventoryMovement', () {
    test('fromJson parses correctly', () {
      final json = {
        'id': 'm1',
        'product_name': 'Roundup',
        'type': 'inbound',
        'quantity': 50,
        'previous_quantity': 0,
        'new_quantity': 50,
        'created_at': '2024-01-15',
      };
      final m = InventoryMovement.fromJson(json);
      expect(m.productName, 'Roundup');
      expect(m.quantity, 50);
      expect(m.isInbound, true);
      expect(m.isOutbound, false);
    });

    test('isInbound detects various inbound types', () {
      const received = InventoryMovement(
        id: '1',
        productName: 'A',
        type: 'received',
        quantity: 10,
        previousQuantity: 0,
        newQuantity: 10,
        createdAt: '',
      );
      expect(received.isInbound, true);

      const saleOut = InventoryMovement(
        id: '1',
        productName: 'A',
        type: 'sale',
        quantity: 10,
        previousQuantity: 50,
        newQuantity: 40,
        createdAt: '',
      );
      expect(saleOut.isOutbound, true);
    });
  });
}
