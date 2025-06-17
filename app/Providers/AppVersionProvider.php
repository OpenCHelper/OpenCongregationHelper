<?php

namespace App\Providers;

use Awcodes\FilamentVersions\Providers\Contracts\VersionProvider;

class AppVersionProvider implements VersionProvider
{
    protected ?string $branch = null;
    protected ?string $commit = null;
    protected ?string $commitShort = null;

    /**
     * Create a new version provider instance.
     *
     * This will read the current git branch and commit hash from the configured files.
     */
    public function __construct()
    {
        $this->branch = $this->getGitBranch();
        $this->commit = $this->getGitCommitHash($this->branch);
        $this->commitShort = $this->commit ? substr($this->commit, 0, 7) : null;
    }

    public function getName(): string
    {
        return 'App';
    }
 
    public function getVersion(): string
    {
        $git_details = " (" . ($this->branch ?? '') . "#" . ($this->commitShort ?? 'unknown') . ")";
        return config('app.version') . $git_details;
    }

    /**
     * Get the current git branch name.
     * 
     * By default, it reads from the `.git/HEAD` file.
     * You can change the file path by setting the `APP_VERSION_BRANCH_FILE` environment variable.
     */
    private function getGitBranch(): ?string
    {
        $branch_file = config('app.version_info.branch_file') ?: '.git/HEAD';

        if (empty($branch_file) || !is_string($branch_file) || !file_exists(base_path($branch_file)) || !is_readable(base_path($branch_file))) {
            return null;
        }

        // Remove the prefix `ref: `
        $branch = trim(file_get_contents(base_path($branch_file)));
        if (str_starts_with($branch, 'ref: ')) {
            $branch = substr($branch, 5);
        }

        // Clean up the branch name by removing the `refs/*/` prefixes
        if (str_starts_with($branch, 'refs/heads/')) {
            $branch = substr($branch, 11);
        } elseif (str_starts_with($branch, 'refs/tags/')) {
            $branch = substr($branch, 10);
        }
        
        if (empty($branch) || !is_string($branch)) {
            return null;
        }

        return $branch;
    }

    /**
     * Get the commit hash for the given branch (if available).
     * 
     * By default, it reads from the `.git/refs/heads/{branch}` file.
     * You can change the file path by setting the `APP_VERSION_COMMIT_FILE` environment variable.
     * 
     * If no commit file is specified, it will try to read from the default path for the branch.
     * 
     * @param string|null $branch The branch name to get the commit hash for. If null, it will use the branch `main`.
     */
    private function getGitCommitHash(?string $branch = null): ?string
    {
        $commit_file = config('app.version_info.commit_file');

        // If no commit file is specified, use the default git path for the branch (if available)
        if (!empty($branch) && ($commit_file == null || empty($commit_file))) {
            $commit_file = '.git/refs/heads/' . $branch;
        }

        if (empty($commit_file) || !is_string($commit_file) || !file_exists(base_path($commit_file)) || !is_readable(base_path($commit_file))) {
            return null;
        }

        return trim(file_get_contents(base_path($commit_file))) ?: null;
    }
}
