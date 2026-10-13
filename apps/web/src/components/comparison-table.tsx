import Link from "next/link";
import { apcLabel, supportedNames, type CompareEntry } from "../lib/discovery";
export function ComparisonTable({ entries, remove }: {
    entries: CompareEntry[];
    remove: (id: string) => void;
}) {
    const rows: [
        string,
        (entry: CompareEntry) => string
    ][] = [['Publisher', e => e.journal.publisher?.name ?? "Not available"], ['Relevance score', e => e.recommendation ? e.recommendation.relevance_score.toLocaleString("en", { maximumFractionDigits: 2 }) : "Not available"], ['Subjects', e => e.journal.subjects.map(v => v.name).join(", ") || "Not available"], ['Languages', e => supportedNames(e.journal.languages)], ['Article types', e => supportedNames(e.journal.article_types)], ['APC', e => apcLabel(e.journal.apc)], ['Indexation', e => supportedNames(e.journal.indexations)]];
    return <div className="comparison-scroll" tabIndex={0} role="region" aria-label="Journal comparison table"><table className="comparison-table"><caption>Published journal metadata, side by side</caption><thead><tr><th scope="col">Compare</th>{entries.map(e => <th scope="col" key={e.journal.id}><Link href={`/journals/${e.journal.id}`}>{e.journal.title}</Link><button className="text-link remove-button" type="button" onClick={() => remove(e.journal.id)} aria-label={`Remove ${e.journal.title} from comparison`}>Remove</button></th>)}</tr></thead><tbody>{rows.map(([label, value]) => <tr key={label}><th scope="row">{label}</th>{entries.map(e => <td key={e.journal.id}>{value(e)}</td>)}</tr>)}</tbody></table></div>;
}
