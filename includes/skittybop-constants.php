<?php
// Exit if accessed directly.
defined('ABSPATH') || exit;

const SKITTYBOP_SITE_NAME= "Skittybop Video Call";
const SKITTYBOP_APP_NAME= "Skittybop API Management Platform";

const SKITTYBOP_SITE_URL= 'https://skittybop.com';
const SKITTYBOP_APP_URL= 'https://app.skittybop.com';
const SKITTYBOP_APP_API_URL = 'https://api.skittybop.com/api';
const SKITTYBOP_SERVER_DOMAIN = 'meet.skittybop.com';

const SKITTYBOP_TABLE_CALLS = "skittybop_calls";
const SKITTYBOP_BUTTON = "skittybopButton";

class SkittybopMenus
{
    const SETTINGS = "skittybop-settings";
    const OPERATORS = "skittybop-operators";
}

class SkittybopErrorCodes
{
    const INVALID_REQUEST = -1;
    const SERVICE_UNAVAILABLE = -2;
    const NO_AVAILABLE_MINUTES = -3;
}

class SkittybopCallStatus
{
    const PENDING = 0;
    const ACCEPTED = 1;
    const REJECTED = 2;
    const FAILED = 3;
    const CANCELED = 4;
}

class SkittybopImage
{
    const CAMERA = "data:image/svg+xml;base64,PD94bWwgdmVyc2lvbj0iMS4wIiBlbmNvZGluZz0iVVRGLTgiPz4KPHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHhtbG5zOnhsaW5rPSJodHRwOi8vd3d3LnczLm9yZy8xOTk5L3hsaW5rIiB3aWR0aD0iNTBweCIgaGVpZ2h0PSI1MHB4IiB2aWV3Qm94PSIwIDAgNTAgNTAiIHZlcnNpb249IjEuMSI+CjxnIGlkPSJzdXJmYWNlMSI+CjxwYXRoIHN0eWxlPSIgc3Ryb2tlOm5vbmU7ZmlsbC1ydWxlOm5vbnplcm87ZmlsbDpyZ2IoMTAwJSwxMDAlLDEwMCUpO2ZpbGwtb3BhY2l0eToxOyIgZD0iTSA0OC42Nzk2ODggMTMuNjcxODc1IEMgNDcuODMyMDMxIDEzLjEzMjgxMiA0Ni43NTc4MTIgMTMuMDU0Njg4IDQ1LjgyODEyNSAxMy41MTU2MjUgTCAzOC4yODEyNSAxNy4yOTY4NzUgTCAzOC4yODEyNSAxMy4yMTQ4NDQgQyAzOC4yODEyNSA5Ljk4MDQ2OSAzNS42NjQwNjIgNy4zNjMyODEgMzIuNDI5Njg4IDcuMzYzMjgxIEwgNS44NTE1NjIgNy4zNjMyODEgQyAyLjYxNzE4OCA3LjM2MzI4MSAwIDkuOTgwNDY5IDAgMTMuMjE0ODQ0IEwgMCAzNi43ODkwNjIgQyAwIDQwLjAxOTUzMSAyLjYxNzE4OCA0Mi42MzY3MTkgNS44NTE1NjIgNDIuNjM2NzE5IEwgMzIuMzQzNzUgNDIuNjM2NzE5IEMgMzUuNTc4MTI1IDQyLjYzNjcxOSAzOC4xOTUzMTIgNDAuMDE5NTMxIDM4LjE5NTMxMiAzNi43ODkwNjIgTCAzOC4xOTUzMTIgMzIuNzAzMTI1IEwgNDUuNzQyMTg4IDM2LjQ4NDM3NSBDIDQ2LjEyMTA5NCAzNi43MTg3NSA0Ni41OTM3NSAzNi43OTY4NzUgNDcuMDUwNzgxIDM2Ljc5Njg3NSBDIDQ3LjU4NTkzOCAzNi43OTY4NzUgNDguMTI1IDM2LjY0MDYyNSA0OC41OTM3NSAzNi4zMzU5MzggQyA0OS40NDE0MDYgMzUuODAwNzgxIDQ5Ljk4MDQ2OSAzNC44NzUgNDkuOTgwNDY5IDMzLjc5Njg3NSBMIDQ5Ljk4MDQ2OSAxNi4xNDA2MjUgQyA1MC4wNzAzMTIgMTUuMTM2NzE5IDQ5LjUzMTI1IDE0LjIxODc1IDQ4LjY3OTY4OCAxMy42NzE4NzUgWiBNIDQ4LjY3OTY4OCAxMy42NzE4NzUgIi8+CjwvZz4KPC9zdmc+Cg==";
    const TRASH = "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAGAAAABgCAYAAADimHc4AAAACXBIWXMAAAsTAAALEwEAmpwYAAACf0lEQVR4nO3dMW4UQRCF4UogwD4IcAIwGYZ7ESIBFhwHAxk2B4CLwEJgjPSjkcZJa7HEdG29mp76IkelV693PZv0rlkppZRSSimlJAK8oN9L9R5bLv9GHYKw/Bt1CMLyxz4E4AnwEfjF+H7Ou55YovJ/sz1XwOMMB/CJ7TrPcADTW3Krdur+6wDU5gfSVp1nOICT+YG0NVfAI8tg+jQAfJj+JzK+3fTKT1N+KaWUUkoppTA4y47BWXYMzrJjcJYdg7PsGJxlx+AsOwZn2S3c6zNwCtwDjua/Lxx7c5tv2S0s586eOXeByyUlHXK+ZbegoKe3zHq+YN5B51t2Cwo6umXW8YJ5B51v2XkvRKfovHLqglrReeW8F6JTdF45dUGt6Lxy3gvRKTqvnLqgVnReOe+F6BSdV05dUCs6r5z3QnSKziunLqgVnVfOeyE6ReeVUxfUis4r570QnaLzyqkLakXnlfNeiE7ReeXUBbWi88p5L0Sn6Lxy6oJa0XnlvBeiU3ReOXVBrei8ct4L0Sk6r5y6oFZ0XjnvhegUnVdOXVArOq+c90J0is4rpy6oFZ1XznshOkXnlVMX1IrOK+e9EJ2i88qpC2pF55XzXohO0Xnl1AW1ovPKeS9Ep+i8cuqCWtF55bwXolN0Xjl1Qa3ovHL/uxB1SU9+AKcHvqbqOt+yW1DQxXRp+h8Xqb8smHfQ+ZbdwpIugWfzvd3j+ZXpUb77fMsO+MG4vlt2wDfG9dWyA84Y1yvLDngI/GE818B9WwPgHeM5s7WYvp9n/pWJUbzf951DaziEt/Nbd62u52fausrf80x4M32CWMlvzOzmrK+BB+r+SimllFJKKTacv4VEEFbjYvOcAAAAAElFTkSuQmCC";
}

class SkittybopRole
{
    const ADMINISTRATOR = 'administrator';
    const OPERATOR = 'operator';
}

class SkittybopRoleLabel
{
    const OPERATOR = 'Operator';
}

class SkittybopCapability
{
    const MANAGE = "manage_skittybop";
    const MANAGE_OPERATORS = "manage_skittybop_operators";
    const MANAGE_SETTINGS = "manage_skittybop_settings";
}

class SkittybopOption
{
    const ENABLED = "skittybop_enabled";
    const VERSION = "skittybop_version";
    const ONLINE = "skittybop_online";
}