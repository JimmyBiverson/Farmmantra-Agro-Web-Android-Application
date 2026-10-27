import 'package:flutter/material.dart';
import '../core/exceptions/app_exception.dart';
import '../models/order.dart';
import '../models/product.dart';
import '../services/api/api_service.dart';

class OrderProvider extends ChangeNotifier {
  final ApiService _apiService;

  OrderProvider({required ApiService apiService})
      : _apiService = apiService;

  List<Order> _orders = [];
  Order? _selectedOrder;
  Order? _lastCreatedOrder;
  bool _isLoading = false;
  String? _error;
  final Map<String, int> _cartItems = {};
  Map<String, dynamic> _staffDashboard = {};

  List<Order> get orders => _orders;
  Order? get selectedOrder => _selectedOrder;
  Order? get lastCreatedOrder => _lastCreatedOrder;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isEmpty => _orders.isEmpty && !_isLoading && _error == null;
  Map<String, int> get cartItems => Map.unmodifiable(_cartItems);
  Map<String, dynamic> get staffDashboard => _staffDashboard;

  Future<void> loadStaffDashboard({bool silent = false}) async {
    try {
      _staffDashboard = await _apiService.getStaffDashboardStats();
      if (!silent) notifyListeners();
    } catch (e) {
      if (!silent) _error = errorMessageOf(e, 'Failed to load staff dashboard');
      notifyListeners();
    }
  }

  Future<void> loadOrders({String? status, String? franchiseId, bool silent = false}) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      _orders = await _apiService.getOrders(status: status, franchiseId: franchiseId);
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) _error = errorMessageOf(e, 'Failed to load orders');
      notifyListeners();
    }
  }

  Future<void> loadOrder(String id) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _selectedOrder = await _apiService.getOrder(id);
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to load order details');
      notifyListeners();
    }
  }

  Future<bool> createOrder(Map<String, dynamic> orderData) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final order = await _apiService.createOrder(orderData);
      _lastCreatedOrder = order;
      _orders.insert(0, order);
      clearCart();
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to create order');
      notifyListeners();
      return false;
    }
  }

  Future<bool> approveOrder(String id,
      {String? deliveryDate, String? notes}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final updated =
          await _apiService.approveOrder(id, deliveryDate: deliveryDate, notes: notes);
      _replaceOrder(updated);
      if (_selectedOrder?.id == id) _selectedOrder = updated;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to approve order');
      notifyListeners();
      return false;
    }
  }

  Future<bool> declineOrder(String id, String reason) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final updated = await _apiService.declineOrder(id, reason);
      _replaceOrder(updated);
      if (_selectedOrder?.id == id) _selectedOrder = updated;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to decline order');
      notifyListeners();
      return false;
    }
  }

  Future<bool> adjustOrder(
      String id, Map<String, dynamic> adjustments) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final updated = await _apiService.adjustOrder(id, adjustments);
      _replaceOrder(updated);
      if (_selectedOrder?.id == id) _selectedOrder = updated;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to adjust order');
      notifyListeners();
      return false;
    }
  }

  Future<bool> confirmDelivery(
    String id, {
    String? notes,
    Map<String, double>? receivedQuantities,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final updated = await _apiService.confirmDelivery(
        id,
        notes: notes,
        receivedQuantities: receivedQuantities,
      );
      _replaceOrder(updated);
      if (_selectedOrder?.id == id) _selectedOrder = updated;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to confirm delivery');
      notifyListeners();
      return false;
    }
  }

  Future<bool> dispatchOrder(
    String id, {
    String means = 'truck',
    String? vehicle,
    String? remarks,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final updated = await _apiService.dispatchOrder(
        id,
        means: means,
        vehicle: vehicle,
        remarks: remarks,
      );
      _replaceOrder(updated);
      if (_selectedOrder?.id == id) _selectedOrder = updated;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to dispatch order');
      notifyListeners();
      return false;
    }
  }

  Future<bool> markDelivered(String id) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final updated = await _apiService.markOrderDelivered(id);
      _replaceOrder(updated);
      if (_selectedOrder?.id == id) _selectedOrder = updated;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to mark order as delivered');
      notifyListeners();
      return false;
    }
  }

  Future<bool> declineDelivery(String id, String reason) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final updated = await _apiService.declineDelivery(id, reason);
      _replaceOrder(updated);
      if (_selectedOrder?.id == id) _selectedOrder = updated;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to decline delivery');
      notifyListeners();
      return false;
    }
  }

  // Cart methods

  void addToCart(String productId, int qty) {
    _cartItems[productId] = (_cartItems[productId] ?? 0) + qty;
    notifyListeners();
  }

  void removeFromCart(String productId) {
    _cartItems.remove(productId);
    notifyListeners();
  }

  void updateCartQuantity(String productId, int qty) {
    if (qty <= 0) {
      _cartItems.remove(productId);
    } else {
      _cartItems[productId] = qty;
    }
    notifyListeners();
  }

  void clearCart() {
    _cartItems.clear();
    notifyListeners();
  }

  double getCartTotal(List<Product> products) {
    double total = 0;
    for (final entry in _cartItems.entries) {
      final product = products.where((p) => p.id == entry.key).firstOrNull;
      if (product != null) {
        total += product.getPriceForQuantity(entry.value) * entry.value;
      }
    }
    return total;
  }

  List<Map<String, dynamic>> getCartItems() {
    return _cartItems.entries
        .map((e) => {'product_id': e.key, 'quantity': e.value})
        .toList();
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  void _replaceOrder(Order updated) {
    final index = _orders.indexWhere((o) => o.id == updated.id);
    if (index != -1) {
      _orders[index] = updated;
    }
  }
}
