import 'package:flutter_test/flutter_test.dart';
import 'package:farmmantra/providers/payment_provider.dart';
import 'package:farmmantra/services/api/mock_api_service.dart';

void main() {
  late MockApiService apiService;
  late PaymentProvider provider;

  setUp(() async {
    apiService = MockApiService();
    await apiService.initialize();
    provider = PaymentProvider(apiService: apiService);
  });

  group('PaymentProvider', () {
    test('initial state', () {
      expect(provider.payments, isEmpty);
      expect(provider.accountSummary, isNull);
      expect(provider.isLoading, false);
      expect(provider.isEmpty, true);
    });

    test('loadPayments populates payments', () async {
      await provider.loadPayments();
      expect(provider.payments, isNotEmpty);
      expect(provider.isLoading, false);
    });

    test('pendingPayments filters correctly', () async {
      await provider.loadPayments();
      expect(
        provider.pendingPayments.every((p) => p.statusEnum.name == 'pending'),
        true,
      );
    });

    test('totalPendingAmount sums correctly', () async {
      await provider.loadPayments();
      final expected = provider.pendingPayments.fold(
        0.0,
        (s, p) => s + p.amount,
      );
      expect(provider.totalPendingAmount, expected);
    });

    test('submitPayment adds payment to list', () async {
      await provider.loadPayments();
      final count = provider.payments.length;

      final success = await provider.submitPayment({
        'amount': 500000,
        'payment_method': 'bank_transfer',
        'transaction_reference': 'REF-NEW',
      });

      expect(success, true);
      expect(provider.payments.length, count + 1);
    });

    test('loadAccountSummary populates summary', () async {
      await provider.loadAccountSummary();
      expect(provider.accountSummary, isNotNull);
      expect(provider.accountSummary!.creditLimit, 20000000);
    });

    test('loadFinancePayments populates payments', () async {
      await provider.loadFinancePayments();
      expect(provider.payments, isNotEmpty);
    });

    test('loadFinanceDashboard populates dashboard', () async {
      await provider.loadFinanceDashboard();
      expect(provider.financeDashboard, isNotEmpty);
    });

    test('loadPendingFinancePayments populates pending', () async {
      await provider.loadPendingFinancePayments();
      expect(provider.pendingFinancePayments, isNotEmpty);
    });

    test('verifyPaymentById updates payment status', () async {
      await provider.loadPayments();
      final pendingPayment = provider.payments.firstWhere(
        (p) => p.statusEnum.name == 'pending',
        orElse: () => provider.payments.first,
      );

      final success = await provider.verifyPaymentById(pendingPayment.id);
      expect(success, true);
    });

    test('clearError clears the error', () async {
      provider.clearError();
      expect(provider.error, isNull);
    });
  });
}
