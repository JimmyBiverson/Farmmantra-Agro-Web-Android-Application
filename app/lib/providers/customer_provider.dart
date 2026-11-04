import 'package:flutter/material.dart';
import '../core/exceptions/app_exception.dart';
import '../models/customer.dart';
import '../services/api/api_service.dart';

class CustomerProvider extends ChangeNotifier {
  final ApiService _apiService;

  CustomerProvider({required ApiService apiService}) : _apiService = apiService;

  List<Customer> _customers = [];
  Customer? _selectedCustomer;
  bool _isLoading = false;
  String? _error;

  List<Customer> get customers => _customers;
  Customer? get selectedCustomer => _selectedCustomer;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isEmpty => _customers.isEmpty && !_isLoading && _error == null;

  Future<void> loadCustomers({bool silent = false}) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      _customers = await _apiService.getCustomers();
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) _error = errorMessageOf(e, 'Failed to load customers');
      notifyListeners();
    }
  }

  Future<void> loadCustomer(String id) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      _selectedCustomer = await _apiService.getCustomer(id);
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to load customer details');
      notifyListeners();
    }
  }

  Future<bool> createCustomer(Map<String, dynamic> data) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final customer = await _apiService.createCustomer(data);
      _customers.insert(0, customer);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to create customer');
      notifyListeners();
      return false;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
