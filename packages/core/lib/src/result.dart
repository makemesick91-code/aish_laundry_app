import 'package:meta/meta.dart';

import 'failure.dart';

/// The outcome of an operation that is expected to fail some of the time.
///
/// Every fallible boundary in this codebase returns a [Result] rather than
/// throwing. The reason is not stylistic: an exception is invisible in a
/// function's type, so a caller can forget the failure path and the analyser
/// will not say a word. A [Result] forces the caller to name both outcomes.
///
/// Programmer errors — a broken invariant, a null that cannot be null — still
/// throw. [Result] models *expected* failure, not *impossible* state.
@immutable
sealed class Result<T> {
  const Result();

  /// A successful outcome carrying [value].
  const factory Result.ok(T value) = Ok<T>;

  /// A failed outcome carrying [failure].
  const factory Result.err(Failure failure) = Err<T>;

  bool get isOk => this is Ok<T>;

  bool get isErr => this is Err<T>;

  /// The value when successful, otherwise `null`.
  ///
  /// Deliberately nullable rather than throwing: a caller that wants the
  /// failure handled should pattern-match instead of reaching for the value.
  T? get valueOrNull => switch (this) {
    Ok<T>(:final value) => value,
    Err<T>() => null,
  };

  /// The failure when unsuccessful, otherwise `null`.
  Failure? get failureOrNull => switch (this) {
    Ok<T>() => null,
    Err<T>(:final failure) => failure,
  };

  /// Collapse both branches into a single value.
  R fold<R>(R Function(T value) onOk, R Function(Failure failure) onErr) =>
      switch (this) {
        Ok<T>(:final value) => onOk(value),
        Err<T>(:final failure) => onErr(failure),
      };

  /// Transform the success value, leaving a failure untouched.
  Result<R> map<R>(R Function(T value) transform) => switch (this) {
    Ok<T>(:final value) => Ok<R>(transform(value)),
    Err<T>(:final failure) => Err<R>(failure),
  };

  /// Chain another fallible operation onto a success.
  Result<R> flatMap<R>(Result<R> Function(T value) transform) => switch (this) {
    Ok<T>(:final value) => transform(value),
    Err<T>(:final failure) => Err<R>(failure),
  };
}

/// A successful [Result].
@immutable
final class Ok<T> extends Result<T> {
  const Ok(this.value);

  final T value;

  @override
  bool operator ==(Object other) =>
      identical(this, other) || (other is Ok<T> && other.value == value);

  @override
  int get hashCode => Object.hash(Ok, value);

  @override
  String toString() => 'Ok<$T>($value)';
}

/// A failed [Result].
@immutable
final class Err<T> extends Result<T> {
  const Err(this.failure);

  final Failure failure;

  @override
  bool operator ==(Object other) =>
      identical(this, other) || (other is Err<T> && other.failure == failure);

  @override
  int get hashCode => Object.hash(Err, failure);

  @override
  String toString() => 'Err<$T>($failure)';
}
