import 'package:meta/meta.dart';

/// The metadata projection of a stored QC defect-photo (FR-083, server
/// `QualityControlEvidenceController::projection`). Carries NO bytes, no storage
/// key, and no permanent URL — a stored photo is read only through a short-lived
/// signed URL requested separately (Rule 32, minimal exposure).
@immutable
final class QcEvidence {
  const QcEvidence({
    required this.id,
    required this.inspectionId,
    required this.contentType,
    required this.byteSize,
    required this.checksumSha256,
    required this.status,
    this.uploadedAt,
  });

  factory QcEvidence.fromJson(Map<String, Object?> json) => QcEvidence(
    id: json['id']! as String,
    inspectionId: json['inspection_id']! as String,
    contentType: json['content_type']! as String,
    byteSize: (json['byte_size']! as num).toInt(),
    checksumSha256: json['checksum_sha256']! as String,
    status: json['status']! as String,
    uploadedAt: json['uploaded_at'] as String?,
  );

  final String id;
  final String inspectionId;
  final String contentType;
  final int byteSize;

  /// The server-computed SHA-256 of the stored bytes — the integrity anchor.
  final String checksumSha256;
  final String status;
  final String? uploadedAt;
}

/// A short-lived signed URL for one evidence object (server `.../url` response).
@immutable
final class QcEvidenceUrl {
  const QcEvidenceUrl({required this.url, required this.expiresInSeconds});

  factory QcEvidenceUrl.fromJson(Map<String, Object?> json) => QcEvidenceUrl(
    url: json['url']! as String,
    expiresInSeconds: (json['expires_in']! as num).toInt(),
  );

  final String url;
  final int expiresInSeconds;
}
