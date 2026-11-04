import 'package:intl/intl.dart';

class Formatters {
  Formatters._();

  static final _currencyFormat = NumberFormat('#,##0');
  static final _decimalFormat = NumberFormat('#,##0.00');
  static final _dateFormat = DateFormat('dd MMM yyyy');
  static final _dateTimeFormat = DateFormat('dd MMM yyyy, hh:mm a');
  static final _shortDateFormat = DateFormat('dd/MM/yyyy');
  static final _monthYearFormat = DateFormat('MMM yyyy');

  static String currency(dynamic amount) {
    if (amount == null) return 'UGX 0';
    final num value = amount is String ? (num.tryParse(amount) ?? 0) : amount;
    return 'UGX ${_currencyFormat.format(value)}';
  }

  static String currencyPlain(dynamic amount) {
    if (amount == null) return '0';
    final num value = amount is String ? (num.tryParse(amount) ?? 0) : amount;
    return _currencyFormat.format(value);
  }

  static String decimal(dynamic value) {
    if (value == null) return '0.00';
    final num numValue = value is String ? (num.tryParse(value) ?? 0) : value;
    return _decimalFormat.format(numValue);
  }

  static DateTime? _parseDateTime(dynamic value) {
    if (value == null) return null;
    if (value is DateTime) return value;
    if (value is String) {
      return DateTime.tryParse(value);
    }
    return null;
  }

  static String date(dynamic dateTime) {
    final dt = _parseDateTime(dateTime);
    if (dt == null) return '';
    return _dateFormat.format(dt);
  }

  static String dateTime(dynamic dateTime) {
    final dt = _parseDateTime(dateTime);
    if (dt == null) return '';
    return _dateTimeFormat.format(dt);
  }

  static String shortDate(dynamic dateTime) {
    final dt = _parseDateTime(dateTime);
    if (dt == null) return '';
    return _shortDateFormat.format(dt);
  }

  static String monthYear(dynamic dateTime) {
    final dt = _parseDateTime(dateTime);
    if (dt == null) return '';
    return _monthYearFormat.format(dt);
  }

  static String percentage(dynamic value) {
    if (value == null) return '0%';
    final num numValue = value is String ? (num.tryParse(value) ?? 0) : value;
    return '${_decimalFormat.format(numValue)}%';
  }

  static String quantity(dynamic value) {
    if (value == null) return '0';
    final num numValue = value is String ? (num.tryParse(value) ?? 0) : value;
    if (numValue == numValue.roundToDouble()) {
      return _currencyFormat.format(numValue.toInt());
    }
    return _decimalFormat.format(numValue);
  }

  /// Parses any numeric value (String like "22000.00", num, or int) into a
  /// [num]. Falls back to [fallback] (default 0) for null/unparseable values.
  static num toNum(dynamic value, {num fallback = 0}) {
    if (value == null) return fallback;
    if (value is num) return value;
    if (value is String) {
      return num.tryParse(value.trim()) ?? fallback;
    }
    return fallback;
  }

  /// Parses a numeric value into a [double]. Never throws.
  static double toDouble(dynamic value, {double fallback = 0}) =>
      toNum(value, fallback: fallback).toDouble();

  /// Parses a numeric value into an [int]. Never throws.
  static int toInt(dynamic value, {int fallback = 0}) =>
      toNum(value, fallback: fallback).toInt();
}
