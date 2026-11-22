---
name: release
description: Release a new Pods version, from the version bump PR to the published and verified image.
argument-hint: '[version, such as v1.3.0]'
disable-model-invocation: true
---

# Release Pods

The version to release is `$ARGUMENTS`. If that's empty, propose one from the changes since the latest release and wait for the user's answer.

Release tags are immutable: a botched release needs a new version. Every 1.x release keeps data on upgrade and stays on lpod contract 1.

Work out where the release is first (bump PR open, merged, or already published) and continue from that step.

## 1. Preflight

- Run `git fetch origin`, then read `gh release list --limit 3`, `git log --oneline <latest tag>..origin/main` and `gh pr list`. Tell the user about open PRs that look meant for this release.
- If Pods needs a newer lpod, that lpod release must be published already: `gh release view <lpod tag> --repo foxws/lpod`. Stop if it isn't, or if it still needs a check on a real host.

## 2. Bump PR

Branch from `origin/main` (such as `release-v1-3-0`). Always bump, even for a fix-only release, or the units keep running the previous image.

- `install.sh`: `PODS_REF`, the URLs in the header, the help text and the `pods-update` example.
- `containers/quadlets/production/*.container`: the image tags, without the `v`.
- `config/catalog.php`: `reference`.
- `README.md` and `docs/installation.md`: the install URLs.
- For a new lpod only: `LPOD_REF` and the help text in `install.sh`, and `services.pods.lpod_version`.

Run `bash .ai/skills/release/verify-pins.sh <version>`, then open the PR as `Release <version>`, adding `, with lpod <tag>` when that changed. The user merges it.

## 3. Check the merge commit

- Get it with `gh pr view <number> --json state,mergeCommit`, then `git fetch origin`. Use that SHA from here on, not `main`, which can move.
- Run `bash .ai/skills/release/verify-pins.sh <version> <sha>`.
- `gh run list --commit <sha>` must show Tests, Code Quality and Build completed successfully. Wait for runs in progress, and stop on a failure.

## 4. Publish

Write the notes to a file outside the repository, shaped like the previous release's (`gh release view`): a summary, the changes, what to do before updating, and the upgrade steps. Leave out private domains, host names and the owner's own applications.

Show the user the version, the SHA and the notes, and **wait for an explicit yes**. Then run:

```bash
gh release create <version> --target <sha> --title <version> --notes-file <file> --generate-notes --latest
```

For a pre-release, use `--prerelease` instead of `--latest`.

## 5. Verify

- Wait for the Build run of the `release` event to succeed: `gh run list --workflow Build --event release --limit 1`.
- The tags `X.Y.Z`, `X.Y`, `X` and `latest` must have the same digest, with amd64 and arm64 (a pre-release only has its own tag):

```bash
token=$(curl -fsS 'https://ghcr.io/token?scope=repository:foxws/pods:pull' | jq -r .token)
accept='application/vnd.oci.image.index.v1+json, application/vnd.docker.distribution.manifest.list.v2+json'
for tag in X.Y.Z X.Y X latest; do
  manifest=$(curl -fsS -H "Authorization: Bearer $token" -H "Accept: $accept" "https://ghcr.io/v2/foxws/pods/manifests/$tag")
  echo "$tag $(printf %s "$manifest" | sha256sum | cut -c1-12) $(jq -c '[.manifests[].platform.architecture]' <<<"$manifest")"
done
```

Report the release URL and what you verified. Podman isn't in the dev container, so say that installing and upgrading on a real host is still unchecked.
