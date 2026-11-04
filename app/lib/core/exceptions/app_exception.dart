import 'dart:async';
import 'package:http/http.dart' as http;

class AppException implements Exception {
  final String message;
  final String? code;
  final int? statusCode;

  const AppException({required this.message, this.code, this.statusCode});

  @override
  String toString() =>
      'AppException: $message (code: $code, status: $statusCode)';
}

class NetworkException extends AppException {
  const NetworkException({
    super.message = 'Network error. Please check your connection.',
    super.code = 'NETWORK_ERROR',
    super.statusCode,
  });
}

class AuthException extends AppException {
  const AuthException({
    super.message = 'Authentication failed. Please login again.',
    super.code = 'AUTH_ERROR',
    super.statusCode,
  });
}

class ServerException extends AppException {
  const ServerException({
    super.message = 'Server error. Please try again later.',
    super.code = 'SERVER_ERROR',
    super.statusCode,
  });
}

class ValidationException extends AppException {
  final Map<String, String>? fieldErrors;

  const ValidationException({
    super.message = 'Validation failed.',
    super.code = 'VALIDATION_ERROR',
    super.statusCode,
    this.fieldErrors,
  });
}

class CacheException extends AppException {
  const CacheException({
    super.message = 'Local storage error.',
    super.code = 'CACHE_ERROR',
  });
}

/// Returns a user-friendly message for a caught [error], falling back to
/// [fallback] when the cause is not actionable or known.
String errorMessageOf(Object error, String fallback) {
  if (error is AppException && error.message.trim().isNotEmpty) {
    return error.message;
  }
  if (error is http.ClientException) {
    return 'Network error. Please check your connection and try again.';
  }
  if (error is TimeoutException) {
    return 'The request timed out. Please try again.';
  }
  return fallback;
}
