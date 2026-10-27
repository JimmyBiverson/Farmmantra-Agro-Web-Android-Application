import 'dart:async';
import 'package:flutter/material.dart';
import 'package:http/http.dart' as http;
import '../core/enums/user_role.dart';
import '../core/exceptions/app_exception.dart';
import '../models/user.dart';
import '../services/api/api_service.dart';
import '../services/storage/local_storage_service.dart';
import '../services/notification_service.dart';

class AuthProvider extends ChangeNotifier {
  final ApiService _apiService;
  final LocalStorageService _storage;

  AuthProvider({
    required ApiService apiService,
    required LocalStorageService storage,
  }) : _apiService = apiService,
       _storage = storage;

  User? _user;
  String? _token;
  bool _isLoading = false;
  bool _isInitialized = false;
  String? _error;

  User? get user => _user;
  String? get token => _token;
  bool get isLoading => _isLoading;
  bool get isInitialized => _isInitialized;
  bool get isAuthenticated => _token != null && _user != null;
  String? get error => _error;
  UserRole? get userRole => _user?.role;

  void _syncToken(String? token) {
    _token = token;
    _apiService.setToken(token);
  }

  void _syncRole(UserRole? role) {
    _apiService.setUserRole(role?.name);
  }

  Future<void> initialize() async {
    _isInitialized = false;
    notifyListeners();

    try {
      final savedToken = _storage.getToken();
      if (savedToken != null) {
        _syncToken(savedToken);
        _user = await _apiService.getProfile();
        _syncRole(_user?.role);
        await NotificationService().registerAuthenticatedDevice();
      }
    } catch (e) {
      // Token invalid or network error — clear and proceed to login
      await _storage.clearAuthData();
      _syncToken(null);
      _syncRole(null);
      _user = null;
    } finally {
      _isInitialized = true;
      notifyListeners();
    }
  }

  Future<bool> login(String email, String password) async {
    _isLoading = true;
    _error = null;
    notifyListeners();

    try {
      final response = await _apiService.login(email, password);
      final token = response['token'] as String?;
      _syncToken(token);

      _user = User.fromJson(response['user']);
      _syncRole(_user?.role);
      await NotificationService().registerAuthenticatedDevice();

      await _storage.saveToken(token!);
      if (response['refresh_token'] != null) {
        await _storage.saveRefreshToken(response['refresh_token']);
      }
      await _storage.saveUser(response['user']);

      _isLoading = false;
      notifyListeners();
      return true;
    } on AuthException catch (e) {
      _error = e.message;
      _isLoading = false;
      notifyListeners();
      return false;
    } on AppException catch (e) {
      _error = e.message;
      _isLoading = false;
      notifyListeners();
      return false;
    } catch (e, stackTrace) {
      debugPrint('AuthProvider.login error: $e\n$stackTrace');
      _error = _mapLoginError(e);
      _isLoading = false;
      notifyListeners();
      return false;
    }
  }

  /// Map unexpected exceptions to a useful, user-facing message.
  String _mapLoginError(Object error) {
    final lower = error.toString().toLowerCase();

    if (error is http.ClientException ||
        lower.contains('socketexception') ||
        lower.contains('connection refused') ||
        lower.contains('connection reset') ||
        lower.contains('connection closed')) {
      return 'Cannot connect to server. Check your network and ensure the server is running.';
    }
    if (lower.contains('timeout') || lower.contains('timed out')) {
      return 'Connection timed out. The server may be unreachable.';
    }
    if (error is FormatException) {
      return 'The server returned an unexpected response. Please try again.';
    }
    return 'An unexpected error occurred ($error). Please try again.';
  }

  Future<void> logout() async {
    try {
      await _apiService.logout();
    } catch (_) {
      // Continue with local logout even if API call fails
    }

    _user = null;
    _syncToken(null);
    _syncRole(null);
    _error = null;
    await _storage.clearAuthData();
    notifyListeners();
  }

  /// Called after the franchise accepts the terms & conditions.
  void markTermsAccepted() {
    final current = _user;
    if (current == null) return;
    _user = current.copyWith(needsTermsAcceptance: false);
    notifyListeners();
  }

  Future<void> refreshProfile() async {
    try {
      _user = await _apiService.getProfile();
      notifyListeners();
    } catch (e) {
      // Silently fail - profile will refresh on next app open
    }
  }

  Future<bool> updateProfile(Map<String, dynamic> data) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      _user = await _apiService.updateProfile(data);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to update profile');
      notifyListeners();
      return false;
    }
  }

  Future<bool> uploadAvatar(List<int> bytes, String fileName) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      _user = await _apiService.uploadAvatar(bytes, fileName);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to upload avatar');
      notifyListeners();
      return false;
    }
  }

  Future<bool> changePassword(
    String currentPassword,
    String newPassword,
  ) async {
    _isLoading = true;
    _error = null;
    notifyListeners();
    try {
      await _apiService.changePassword(currentPassword, newPassword);
      _isLoading = false;
      notifyListeners();
      return true;
    } catch (e) {
      _isLoading = false;
      _error = errorMessageOf(e, 'Failed to change password');
      notifyListeners();
      return false;
    }
  }

  Future<void> registerDeviceToken(String token) async {
    try {
      await _apiService.updateDeviceToken(token);
    } catch (_) {
      // Silently ignore registration failures
    }
  }

  void clearError() {
    _error = null;
    notifyListeners();
  }

  bool hasRole(UserRole role) => _user?.role == role;
}
