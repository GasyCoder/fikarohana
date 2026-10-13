"use client";
import { useDiscovery } from "../../components/discovery-context";
import { RecommendationResults } from "../../components/recommendation-results";
export default function RecommendationsPage() {
    const { results, selected, toggle } = useDiscovery();
    return <RecommendationResults results={results} selected={selected} toggle={toggle}/>;
}
