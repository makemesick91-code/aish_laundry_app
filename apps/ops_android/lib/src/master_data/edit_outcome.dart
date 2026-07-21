import 'package:aish_core/aish_core.dart';
import 'package:aish_networking/aish_networking.dart';

/// What happened to a master-data write, in terms a counter surface can act on.
///
/// WHY THIS TAXONOMY EXISTS RATHER THAN A BOOLEAN
/// ----------------------------------------------
/// "It failed, offer a retry button" is wrong for three of the six outcomes
/// below, and dangerously wrong for one of them. Collapsing them into a single
/// error state is how a stale write becomes a silent overwrite: the operator
/// presses "coba lagi", the identical payload is resent, and the edit that
/// caused the conflict is destroyed without anybody seeing it (threat T-12).
///
/// So the outcomes are enumerated, and the sealed hierarchy makes the compiler
/// insist that every surface handles each one. A screen author cannot forget the
/// conflict case, because the switch will not compile without it.
sealed class EditOutcome {
  const EditOutcome();

  /// Whether offering an identical resubmission is a safe recovery.
  ///
  /// FALSE for [EditConflict] specifically and deliberately. Everything else
  /// that is unsafe to resend is unsafe for an ordinary reason — the server will
  /// simply refuse it again — but resending a conflicting write SUCCEEDS and
  /// destroys data. That is why the distinction is carried in the type rather
  /// than left to `Failure.isRetryable` alone.
  bool get allowsIdenticalResubmit => false;
}

/// The write was accepted. [record] is the server's post-write state.
///
/// The surface adopts THIS record, including its new version token. Keeping the
/// version the caller sent would make the next edit conflict with itself.
final class EditSaved<T> extends EditOutcome {
  const EditSaved(this.record);

  final T record;
}

/// The record moved underneath the caller. HTTP 409 / `CONFLICT`.
///
/// The recovery is to RELOAD and re-apply, never to retry. The surface must:
///   * say plainly that somebody else changed this record;
///   * offer reload, and NOT a generic "coba lagi";
///   * keep what the operator typed so it is not lost;
///   * not resubmit anything on its own.
///
/// Rule 07 hard rule 5's principle applied to master data: a conflict is
/// surfaced for a human, never resolved by whoever saved last.
final class EditConflict extends EditOutcome {
  const EditConflict(this.failure);

  final Failure failure;
}

/// The server rejected the CONTENTS of the request. HTTP 422.
///
/// Distinct from a conflict: here something the caller SENT is wrong, so the
/// surface highlights fields. In a conflict nothing the caller sent is wrong —
/// what changed is the record underneath them — so highlighting a field there
/// would send the operator looking for a mistake they did not make.
final class EditRejected extends EditOutcome {
  const EditRejected(this.failure);

  final Failure failure;

  /// Per-field messages the server supplied, if any.
  ///
  /// Read from `details` and never invented. An empty map means the surface
  /// shows the general message rather than guessing which field was at fault.
  Map<String, List<String>> get fieldErrors {
    final out = <String, List<String>>{};

    for (final entry in failure.details.entries) {
      final value = entry.value;
      if (value is List) {
        out[entry.key] = value.map((item) => '$item').toList(growable: false);
      } else if (value is String) {
        out[entry.key] = <String>[value];
      }
    }

    return out;
  }
}

/// The server refused the ACTION. HTTP 403, or a 404 across a tenant boundary.
///
/// Renders identically whether the record belongs to another tenant or does not
/// exist. The server answers the same for both, and a client that distinguished
/// them would leak exactly what the server just hid (Rule 48 hard rule 5).
final class EditDenied extends EditOutcome {
  const EditDenied(this.failure);

  final Failure failure;
}

/// The request never reached a decision: no network, or it timed out.
///
/// The ONLY outcome for which an identical resubmission is safe. The server
/// either did not see the request or did not answer, so the version precondition
/// still holds and resending cannot overwrite a newer edit.
final class EditUnreachable extends EditOutcome {
  const EditUnreachable(this.failure);

  final Failure failure;

  @override
  bool get allowsIdenticalResubmit => true;
}

/// The server answered, but with something this build cannot classify.
///
/// Retryable, because the fail-safe direction for an unknown code is "transient
/// problem", never "you are logged out" and never "you lack permission"
/// (`ApiErrorMapper`'s contract, carried through to the surface).
final class EditUnavailable extends EditOutcome {
  const EditUnavailable(this.failure);

  final Failure failure;

  @override
  bool get allowsIdenticalResubmit => true;
}

/// Classify a repository [Result] into an [EditOutcome].
///
/// THE CONFLICT TEST IS THE SERVER'S MACHINE-READABLE CODE, NOT THE HTTP STATUS
/// AND NOT THE FAILURE KIND. `CONFLICT` maps to `FailureKind.validation` on
/// purpose — that kind is non-retryable, which is the safe default — but so does
/// an ordinary 422, and the two need entirely different recoveries. The code is
/// the only signal that separates them, so it is what is read here.
EditOutcome classifyEdit<T>(Result<T> result) => result.fold(
  EditSaved<T>.new,
  (Failure failure) => switch (failure) {
    _ when failure.code == ApiErrorCode.conflict.wireValue => EditConflict(
      failure,
    ),
    _ when failure.kind == FailureKind.validation => EditRejected(failure),
    _
        when failure.kind == FailureKind.authorization ||
            failure.kind == FailureKind.authentication =>
      EditDenied(failure),
    _
        when failure.kind == FailureKind.network ||
            failure.kind == FailureKind.timeout =>
      EditUnreachable(failure),
    _ => EditUnavailable(failure),
  },
);
