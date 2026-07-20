<?php

// Step 3 exposes no web surface. Every client consumes the versioned HTTP API
// under /api/v1 (Rule 06). This file exists so the router has a defined web
// group; it deliberately registers no route.
