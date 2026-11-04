import 'package:flutter/material.dart';
import '../core/enums/app_enums.dart';
import '../core/exceptions/app_exception.dart';
import '../models/payment.dart';
import '../services/api/api_service.dart';

class PaymentProvider extends ChangeNotifier {
  final ApiService _apiService;

  PaymentProvider({required ApiService apiService}) : _apiService = apiService;

  List<Payment> _payments = [];
  AccountSummary? _accountSummary;
  bool _isLoading = false;
  String? _error;

  // Finance-specific state
  Map<String, dynamic> _financeDashboard = {};
  List<Payment> _pendingFinancePayments = [];

  List<Payment> get payments => _payments;
  AccountSummary? get accountSummary => _accountSummary;
  bool get isLoading => _isLoading;
  String? get error => _error;
  bool get isEmpty => _payments.isEmpty && !_isLoading && _error == null;

  Map<String, dynamic> get financeDashboard => _financeDashboard;
  List<Payment> get pendingFinancePayments => _pendingFinancePayments;

  List<Payment> get pendingPayments =>
      _payments.where((p) => p.statusEnum == PaymentStatus.pending).toList();

  double get totalPendingAmount =>
      pendingPayments.fold(0.0, (sum, p) => sum + p.amount);

  List<Payment> get verifiedPayments =>
      _payments.where((p) => p.statusEnum == PaymentStatus.verified).toList();

  List<Payment> get acceptedPayments =>
      _payments.where((p) => p.statusEnum == PaymentStatus.accepted).toList();

  List<Payment> get rejectedPayments =>
      _payments.where((p) => p.statusEnum == PaymentStatus.rejected).toList();

  Future<void> loadPayments({String? status, bool silent = false}) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      _payments = await _apiService.getPayments(status: status);
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) _error = errorMessageOf(e, 'Failed to load payments');
      notifyListeners();
    }
  }

  Future<bool> submitPayment(Map<String, dynamic> data) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final payment = await _apiService.submitPayment(data);
      _payments.insert(0, payment);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to submit payment');
      notifyListeners();
      return false;
    }
  }

  Future<bool> uploadProof(
    String paymentId,
    List<int> bytes,
    String name,
  ) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      await _apiService.uploadPaymentProof(paymentId, bytes, name);
      final updated = await _apiService.getPayment(paymentId);
      final index = _payments.indexWhere((p) => p.id == paymentId);
      if (index != -1) _payments[index] = updated;
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to upload payment proof');
      notifyListeners();
      return false;
    }
  }

  Future<void> loadAccountSummary({bool silent = false}) async {
    try {
      if (!silent) _error = null;
      _accountSummary = await _apiService.getAccountSummary();
      if (!silent) _error = null;
      notifyListeners();
    } catch (e) {
      if (!silent) _error = errorMessageOf(e, 'Failed to load account summary');
      notifyListeners();
    }
  }

  // ══════════════════════════════════════════════════════════════
  // FINANCE-SPECIFIC METHODS
  // ══════════════════════════════════════════════════════════════

  Future<void> loadFinancePayments({
    String? status,
    String? franchiseId,
    bool silent = false,
  }) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      _payments = await _apiService.getFinancePayments(
        status: status,
        franchiseId: franchiseId,
      );
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) _error = errorMessageOf(e, 'Failed to load payments');
      notifyListeners();
    }
  }

  Future<void> loadPendingFinancePayments({bool silent = false}) async {
    if (!silent) {
      _isLoading = true;
      _error = null;
      notifyListeners();
    }

    try {
      _pendingFinancePayments = await _apiService.getPendingFinancePayments();
      _isLoading = false;
      notifyListeners();
    } catch (e) {
      _isLoading = false;
      if (!silent) {
        _error = errorMessageOf(e, 'Failed to load pending payments');
      }
      notifyListeners();
    }
  }

  Future<void> loadFinanceDashboard({bool silent = false}) async {
    try {
      if (!silent) _error = null;
      _financeDashboard = await _apiService.getFinanceDashboardStats();
      if (!silent) _error = null;
      notifyListeners();
    } catch (e) {
      if (!silent) {
        _error = errorMessageOf(e, 'Failed to load finance dashboard');
      }
      notifyListeners();
    }
  }

  Future<bool> verifyPaymentById(
    String id, {
    double? verifiedAmount,
    String? notes,
  }) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final payment = await _apiService.verifyPayment(
        id,
        verifiedAmount: verifiedAmount,
        notes: notes,
      );
      final index = _payments.indexWhere((p) => p.id == id);
      if (index != -1) _payments[index] = payment;
      _pendingFinancePayments.removeWhere((p) => p.id == id);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to verify payment');
      notifyListeners();
      return false;
    }
  }

  Future<bool> acceptPaymentById(String id, {String? notes}) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final payment = await _apiService.acceptPayment(id, notes: notes);
      final index = _payments.indexWhere((p) => p.id == id);
      if (index != -1) _payments[index] = payment;
      _pendingFinancePayments.removeWhere((p) => p.id == id);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to accept payment');
      notifyListeners();
      return false;
    }
  }

  Future<bool> rejectPaymentById(String id, String reason) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final payment = await _apiService.rejectPayment(id, reason);
      final index = _payments.indexWhere((p) => p.id == id);
      if (index != -1) _payments[index] = payment;
      _pendingFinancePayments.removeWhere((p) => p.id == id);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to reject payment');
      notifyListeners();
      return false;
    }
  }

  Future<bool> requestInfoById(String id, String note) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final payment = await _apiService.requestPaymentInfo(id, note);
      final index = _payments.indexWhere((p) => p.id == id);
      if (index != -1) _payments[index] = payment;
      _pendingFinancePayments.removeWhere((p) => p.id == id);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to request payment information');
      notifyListeners();
      return false;
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }
}
