import 'package:flutter/material.dart';
import '../core/exceptions/app_exception.dart';
import '../models/inventory.dart';
import '../services/api/api_service.dart';

class InventoryProvider extends ChangeNotifier {
  final ApiService _apiService;

  InventoryProvider({required ApiService apiService})
    : _apiService = apiService;

  List<InventoryItem> _items = [];
  List<InventoryMovement> _movements = [];
  bool _isLoading = false;
  String? _error;

  List<InventoryItem> get items => _items;
  List<InventoryMovement> get movements => _movements;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isEmpty => _items.isEmpty && !_isLoading && _error == null;

  double get totalValue =>
      _items.fold(0.0, (sum, item) => sum + item.totalValue);

  List<InventoryItem> get lowStockItems =>
      _items.where((i) => i.isLowStock && !i.isOutOfStock).toList();

  List<InventoryItem> get outOfStockItems =>
      _items.where((i) => i.isOutOfStock).toList();

  Future<void> loadInventory({String? franchiseId, bool silent = false}) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      _items = await _apiService.getInventory(franchiseId: franchiseId);
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) _error = errorMessageOf(e, 'Failed to load inventory');
      notifyListeners();
    }
  }

  Future<void> loadMovements({String? productId, bool silent = false}) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      _movements = await _apiService.getInventoryMovements(
        productId: productId,
      );
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) {
        _error = errorMessageOf(e, 'Failed to load inventory movements');
      }
      notifyListeners();
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
