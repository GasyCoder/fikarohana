import { readFile, writeFile, mkdir } from "node:fs/promises";
import { fileURLToPath } from "node:url";
import { createConfig, lint } from "@redocly/openapi-core";
import openapiTS, { astToString, COMMENT_HEADER } from "openapi-typescript";

const schema = new URL("../openapi.yaml", import.meta.url);
const output = new URL("../generated/api.d.ts", import.meta.url);
const action = process.argv[2];
if (!["validate", "generate", "check"].includes(action)) {
  throw new Error("Expected validate, generate or check.");
}
const config = await createConfig({
  extends: ["recommended"],
  rules: { "info-license": "off", "no-server-example.com": "off" }, // Private application; no public API license asserted.
});
const problems = await lint({ ref: fileURLToPath(schema), config });
for (const problem of problems) {
  console.error(`${problem.severity}: ${problem.ruleId}: ${problem.message}`);
}
if (problems.length) {
  process.exitCode = 1;
} else if (action === "validate") {
  console.log("OpenAPI 3.1 contract valid.");
} else {
  const generated = COMMENT_HEADER + astToString(await openapiTS(schema, { redocly: config }));
  if (action === "generate") {
    await mkdir(new URL("../generated/", import.meta.url), { recursive: true });
    await writeFile(output, generated);
    console.log("Generated packages/contracts/generated/api.d.ts.");
  } else {
    if (await readFile(output, "utf8") !== generated) {
      throw new Error("Generated contracts are stale. Run npm run contracts:generate.");
    }
    console.log("Generated contracts are reproducible and current.");
  }
}
