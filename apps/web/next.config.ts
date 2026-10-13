import type { NextConfig } from "next";

const nextConfig: NextConfig = {
  async rewrites() {
    const target = new URL(process.env.FIKAROHANA_API_ORIGIN ?? "http://localhost:8000");
    if (!["http:", "https:"].includes(target.protocol) || target.username || target.password || target.pathname !== "/" || target.search || target.hash) {
      throw new Error("FIKAROHANA_API_ORIGIN must be a Laravel HTTP(S) origin.");
    }
    return [{ source: "/api/v1/:path*", destination: `${target.origin}/api/v1/:path*` }];
  },
  cacheComponents: true,
  partialPrefetching: true,
};

export default nextConfig;
