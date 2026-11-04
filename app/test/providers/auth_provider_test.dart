import 'package:flutter_secure_storage/flutter_secure_storage.dart';
import 'package:flutter_test/flutter_test.dart';
import 'package:shared_preferences/shared_preferences.dart';
import 'package:farmmantra/providers/auth_provider.dart';
import 'package:farmmantra/core/exceptions/app_exception.dart';
import 'package:farmmantra/services/api/mock_api_service.dart';
import 'package:farmmantra/services/storage/local_storage_service.dart';

void main() {
  late MockApiService apiService;
  late LocalStorageService storage;
  late AuthProvider authProvider;

  setUp(() async {
    SharedPreferences.setMockInitialValues({});
    FlutterSecureStorage.setMockInitialValues({});
    apiService = MockApiService();
    await apiService.initialize();
    storage = LocalStorageService();
    await storage.initialize();
    authProvider = AuthProvider(apiService: apiService, storage: storage);
  });

  tearDown(() async {
    await storage.clearAll();
  });

  group('AuthProvider', () {
    test('initial state is not authenticated', () {
      expect(authProvider.isAuthenticated, false);
      expect(authProvider.user, isNull);
      expect(authProvider.token, isNull);
      expect(authProvider.isLoading, false);
    });

    test('login succeeds with valid credentials', () async {
      final result = await authProvider.login(
        'franchise@farmmantra.co.ug',
        'password123',
      );
      expect(result, true);
      expect(authProvider.isAuthenticated, true);
      expect(authProvider.user, isNotNull);
      expect(authProvider.user!.email, 'franchise@farmmantra.co.ug');
      expect(authProvider.token, isNotNull);
    });

    test('login fails with short password', () async {
      final result = await authProvider.login(
        'franchise@farmmantra.co.ug',
        '123',
      );
      expect(result, false);
      expect(authProvider.isAuthenticated, false);
      expect(authProvider.error, isNotNull);
    });

    test('login sets role correctly for staff email', () async {
      await authProvider.login('staff@farmmantra.co.ug', 'password123');
      expect(authProvider.userRole!.isFarmmantraStaff, true);
    });

    test('login sets role correctly for franchise email', () async {
      await authProvider.login('franchise@farmmantra.co.ug', 'password123');
      expect(authProvider.userRole!.isFranchisePartner, true);
    });

    test('logout clears state', () async {
      await authProvider.login('franchise@farmmantra.co.ug', 'password123');
      expect(authProvider.isAuthenticated, true);

      await authProvider.logout();
      expect(authProvider.isAuthenticated, false);
      expect(authProvider.user, isNull);
      expect(authProvider.token, isNull);
    });

    test('clearError clears the error', () async {
      await authProvider.login('franchise@farmmantra.co.ug', '123');
      expect(authProvider.error, isNotNull);

      authProvider.clearError();
      expect(authProvider.error, isNull);
    });

    test('hasRole returns correct result', () async {
      await authProvider.login('franchise@farmmantra.co.ug', 'password123');
      expect(authProvider.hasRole(authProvider.userRole!), true);
    });

    test('notifyListeners is called on login', () async {
      int notifyCount = 0;
      authProvider.addListener(() => notifyCount++);

      await authProvider.login('franchise@farmmantra.co.ug', 'password123');
      expect(notifyCount, greaterThan(0));
    });

    test(
      'login surfaces AppException message instead of generic error',
      () async {
        final throwingApi = _AppExceptionApiService();
        final provider = AuthProvider(
          apiService: throwingApi,
          storage: storage,
        );

        final result = await provider.login(
          'franchise@farmmantra.co.ug',
          'password123',
        );

        expect(result, false);
        expect(provider.error, 'The provided credentials are incorrect.');
      },
    );
  });
}

class _AppExceptionApiService extends MockApiService {
  @override
  Future<Map<String, dynamic>> login(String email, String password) async {
    throw const AppException(
      message: 'The provided credentials are incorrect.',
      code: 'VALIDATION_ERROR',
    );
  }
}
