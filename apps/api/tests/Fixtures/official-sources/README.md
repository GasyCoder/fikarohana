# Official-source fixtures

Synthetic HTML only, created for Phase 3E-A. Names and text are invented; no real journal requirements, credentials or copyrighted guideline copy. Links are untrusted metadata and are never fetched. Scripts are retained as inert internal evidence, never rendered or executed.

stream-server.php is a test-only finite loopback HTTP response harness for native transfer-abort callbacks: no outbound network, application route, credential or production fetch bypass. It serves at most four MiB over one connection and is stopped in test cleanup. Production remains approved-source HTTPS-only; the test keeps TLS verification enabled and adds no custom TLS stack.
