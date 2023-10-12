<?php

class AIParameterType
{
    public const JSON = 1;
}

class AIModel
{
    public const CHAT_GPT_3_5 = 1;
}

class AICurrency
{
    public const EUR = 1;
}

class AIDatabaseTable
{
    public const
        AI_MODELS = "artificial_intelligence.models",
        AI_TEXT_HISTORY = "artificial_intelligence.textHistory",
        AI_PARAMETERS = "artificial_intelligence.parameters",
        AI_CURRENCIES = "artificial_intelligence.currencies";
}