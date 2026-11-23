<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\AssignTerritoryTool;
use App\Mcp\Tools\CreatePublicationRequestTool;
use App\Mcp\Tools\GetMyAccountTool;
use App\Mcp\Tools\GetOverviewTool;
use App\Mcp\Tools\GetTerritoryTool;
use App\Mcp\Tools\ListAssignmentsTool;
use App\Mcp\Tools\ListCampaignsTool;
use App\Mcp\Tools\ListPeopleTool;
use App\Mcp\Tools\ListPublicationRequestsTool;
use App\Mcp\Tools\ListPublicationsTool;
use App\Mcp\Tools\ListTerritoriesTool;
use App\Mcp\Tools\ListTerritoryZonesTool;
use App\Mcp\Tools\RequestTerritoryReturnTool;
use App\Mcp\Tools\ReturnTerritoryTool;
use App\Mcp\Tools\UpdatePublicationRequestStatusTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;

/**
 * The MCP server of the app (`/mcp`, see `routes/ai.php`): it lets the AI assistants that a user connects (e.g.
 * ChatGPT, Claude) help them manage the congregation, with the permissions of that user.
 */
#[Name('Open Congregation Helper')]
#[Instructions(<<<'MARKDOWN'
    This server manages the territories of a congregation of Jehovah's Witnesses: the territories (grouped in zones) are
    assigned to the publishers, who return them once worked. Campaigns are special periods in which territories are
    assigned. The app also keeps the requests of paper publications made by the publishers.

    - Call `get_my_account` first: it tells who the user is and which of their territories are in progress.
    - The tools listed are only the ones the user is allowed to use.
    - Refer to people, territories and campaigns by their name when talking to the user, but use their ids with the
      tools: look them up with the list tools rather than guessing.
    - Before assigning or returning a territory, or recording a request, confirm the details with the user.
    - Dates are `YYYY-MM-DD`, in the timezone of the congregation. Each record has a `url` to open it in the app.
    - The data is personal: do not share it beyond what the user asks for.
    MARKDOWN)]
class CongregationServer extends Server
{
    protected string $version = '1.0.0';

    public int $defaultPaginationLength = 50;

    protected array $tools = [
        GetMyAccountTool::class,
        GetOverviewTool::class,
        ListTerritoriesTool::class,
        GetTerritoryTool::class,
        ListTerritoryZonesTool::class,
        ListAssignmentsTool::class,
        ListCampaignsTool::class,
        ListPeopleTool::class,
        AssignTerritoryTool::class,
        ReturnTerritoryTool::class,
        RequestTerritoryReturnTool::class,
        ListPublicationsTool::class,
        ListPublicationRequestsTool::class,
        CreatePublicationRequestTool::class,
        UpdatePublicationRequestStatusTool::class,
    ];
}
