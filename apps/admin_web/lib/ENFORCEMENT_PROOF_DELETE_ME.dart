// TEMPORARY ENFORCEMENT-PROOF FIXTURE — NOT PRODUCT CODE. DO NOT MERGE.
//
// Purpose: make the `runtime-foundation` required context fail DETERMINISTICALLY
// so that GitHub branch protection can be observed actually BLOCKING a merge.
//
// A workflow going red proves the workflow works. It does not prove the ruleset
// enforces it. This fixture exists only to demonstrate the latter.
//
// The chosen failure is the `dart format --set-exit-if-changed` gate:
//   - deterministic and immediate;
//   - entirely unrelated to product behaviour;
//   - weakens NO security control, tenancy rule, authentication path, RBAC
//     policy, workflow permission, or action pin;
//   - contains no secret, no personal data, and no cross-tenant construct.
//
// This file lives ONLY on test/step-03-negative-enforcement-proof and must never
// reach the canonical Step 3 branch or main.
class   EnforcementProofFixture      {
      final String    deliberatelyBadlyFormatted    =     'this file is not dart-formatted';
   int    x   =   1 ;
       String  describe( )   {  return  'temporary enforcement proof fixture' ;  }
}
