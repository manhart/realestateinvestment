# realestateinvestment

Real estate investment calculator for leveraged residential property scenarios in Germany.

This is a POOL framework application for calculating and evaluating residential real estate investment scenarios with financing, taxes, depreciation, cash flows, NPV, future value, and efficiency metrics.

## Local path

- App path: `/virtualweb/manhart/realestateinvestment`
- Browser path: `http://pofolio.local/manhart/realestateinvestment/`

## Tests

```bash
XDEBUG_MODE=off php8.4 /usr/local/bin/phpunit --do-not-record-test-run-history --configuration phpunit.xml
node tests/ToolbarStateTest.cjs
```

## Scenarios

Select a saved scenario and click **Load** to open it. **Save** updates the
active scenario, including changes to its name or group. **Save a copy**
creates a separate variant without modifying the original. Input changes
recalculate automatically; the browser draft is separate from server saves.

Keep your private workspace link safe. Anyone with that link can view and
edit its saved scenarios. The bookmark action explains how to save the link
in your browser; it does not create a bookmark automatically.
