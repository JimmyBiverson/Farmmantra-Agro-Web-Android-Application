import 'package:flutter/material.dart';
import '../core/exceptions/app_exception.dart';
import '../models/product.dart';
import '../services/api/api_service.dart';

class ProductProvider extends ChangeNotifier {
  final ApiService _apiService;

  ProductProvider({required ApiService apiService}) : _apiService = apiService;

  List<ProductCategory> _categories = [];
  List<Product> _products = [];
  List<Product> _filteredProducts = [];
  String? _selectedCategory;
  bool _isLoading = false;
  String? _error;

  List<ProductCategory> get categories => _categories;
  List<Product> get products =>
      _filteredProducts.isNotEmpty || _searchQuery.isNotEmpty
      ? _filteredProducts
      : _products;
  List<Product> get allProducts => _products;
  String? get selectedCategory => _selectedCategory;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isEmpty => _products.isEmpty && !_isLoading && _error == null;

  String _searchQuery = '';

  /// When [silent] is true a background refresh is performed without showing
  /// the loading state or overwriting an existing error (keeps the UI stable).
  Future<void> loadCategories({bool silent = false}) async {
    if (!silent) _error = null;
    try {
      _categories = await _apiService.getCategories();
      if (!silent) _error = null;
      notifyListeners();
    } catch (e) {
      if (!silent) _error = errorMessageOf(e, 'Failed to load categories');
      notifyListeners();
    }
  }

  /// When [resetFilters] is false the active search query is preserved and
  /// re-applied against the freshly fetched product list.
  Future<void> loadProducts({
    String? categoryId,
    bool silent = false,
    bool resetFilters = true,
  }) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      _products = await _apiService.getProducts(categoryId: categoryId);
      _selectedCategory = categoryId;
      if (resetFilters) {
        _searchQuery = '';
        _filteredProducts = [];
      } else {
        _applyFilter();
      }
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) _error = errorMessageOf(e, 'Failed to load products');
      notifyListeners();
    }
  }

  void selectCategory(String? id) {
    _selectedCategory = id;
    notifyListeners();
    loadProducts(categoryId: id);
  }

  void filterProducts(String query) {
    _searchQuery = query;
    _applyFilter();
    notifyListeners();
  }

  void _applyFilter() {
    if (_searchQuery.isEmpty) {
      _filteredProducts = [];
    } else {
      final lower = _searchQuery.toLowerCase();
      _filteredProducts = _products
          .where(
            (p) =>
                p.name.toLowerCase().contains(lower) ||
                p.categoryName.toLowerCase().contains(lower) ||
                (p.description?.toLowerCase().contains(lower) ?? false),
          )
          .toList();
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
