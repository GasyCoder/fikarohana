import { mkdtempSync, mkdirSync, readFileSync, writeFileSync, rmSync } from "node:fs";
import { tmpdir } from "node:os";
import { dirname, join, delimiter } from "node:path";
import { fileURLToPath } from "node:url";
import { spawnSync } from "node:child_process";
import ts from "typescript";
const web = dirname(dirname(fileURLToPath(import.meta.url)));
const build = mkdtempSync(join(tmpdir(), "fikarohana-web-test-"));
const files = ["lib/discovery.ts", "lib/projects.ts", "components/project-workspace.tsx", "components/project-editor.tsx", "components/auth-context.tsx", "components/discovery-context.tsx", "components/catalogue-options.tsx", "lib/api.ts", "components/ui.tsx", "components/journal-detail.tsx", "components/comparison-table.tsx", "components/recommendation-results.tsx"];
try {
  for (const file of files) {
    const target = join(build, file.replace(/\.tsx?$/, ".js"));
    mkdirSync(dirname(target), { recursive: true });
    const compiled = ts.transpileModule(readFileSync(join(web, "src", file), "utf8"), { compilerOptions: { module: ts.ModuleKind.CommonJS, target: ts.ScriptTarget.ES2020, jsx: ts.JsxEmit.ReactJSX }, fileName: file });
    writeFileSync(target, compiled.outputText);
  }
  const result = spawnSync(process.execPath, ["--test", join(web, "tests/discovery.test.mjs"), join(web, "tests/projects.test.mjs")], { stdio: "inherit", env: { ...process.env, FIKAROHANA_WEB_TEST_BUILD: build, NODE_PATH: [join(web, "node_modules"), join(web, "../../node_modules")].join(delimiter) } });
  process.exitCode = result.status ?? 1;
} finally { rmSync(build, { recursive: true, force: true }); }
