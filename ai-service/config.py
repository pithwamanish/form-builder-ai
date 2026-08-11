"""
AI Microservice Configuration & Prompt Strategy Matrix
Contains system prompts, output contracts, and LLM schema rules.
"""

SYSTEM_PROMPT = """
You are an expert UX & Database Architect. Generate a valid Form Builder JSON schema.
The JSON output MUST follow this structure exactly:
{
  "title": "Form Title",
  "description": "Short summary",
  "sections": [
    {
      "id": "sec_1",
      "title": "Section Header",
      "fields": [
        {
          "id": "fld_1",
          "key": "unique_snake_case_key",
          "type": "text|textarea|number|email|phone|date|time|dropdown|radio|checkbox|file|heading|rating",
          "label": "Human Readable Label",
          "placeholder": "Sample placeholder",
          "required": true,
          "help_text": "Optional help description",
          "options": ["Option 1", "Option 2"],
          "col_span": 12,
          "align": "left",
          "validation": { "min": 1, "max": 100, "email": true }
        }
      ]
    }
  ]
}
Rules:
- Never return code block tags like ```json.
- Always provide clean, sensible field keys, labels, placeholders, and validation rules.
- Supported types: text, textarea, number, email, phone, date, time, dropdown, radio, checkbox, file, heading, rating.
"""

EDIT_FORM_SYSTEM_PROMPT = """
You are an expert UX & Database Architect. You modify existing Form Builder JSON schemas based on user instructions.
Preserve existing structure, section IDs, and field keys where applicable unless asked to remove or alter them.
CRITICAL INSTRUCTIONS:
1. If the user asks to "Add a [Section Name] section" or "Create an [Section Name] section", you MUST create and append a NEW section item in the "sections" array with "title": "[Section Name]".
2. You MUST include ALL requested fields explicitly mentioned in the user instruction (e.g., if asked for "Name, Phone, and Relation", create three distinct field objects for Name, Phone, and Relation).
Return ONLY valid JSON with no markdown code fences.
"""

from pydantic import BaseModel, Field
from typing import List, Optional, Any, Dict

class FieldValidationModel(BaseModel):
    numeric: Optional[bool] = False
    email: Optional[bool] = False
    url: Optional[bool] = False
    min: Optional[float] = None
    max: Optional[float] = None
    min_length: Optional[int] = None
    max_length: Optional[int] = None

class FieldModel(BaseModel):
    id: str = Field(description="Unique field identifier, e.g., fld_1")
    key: str = Field(description="Unique snake_case JSON storage key")
    type: str = Field(description="Field type: text, textarea, number, email, phone, date, time, dropdown, radio, checkbox, file, heading, rating")
    label: str = Field(description="Human readable title label for the input field")
    placeholder: Optional[str] = Field(default="", description="Sample placeholder text inside the input")
    required: Optional[bool] = Field(default=False, description="Whether this field is mandatory")
    help_text: Optional[str] = Field(default="", description="Subtext hint displayed below the field")
    options: Optional[List[str]] = Field(default_factory=list, description="Array of string choices for dropdown, radio, or checkbox")
    col_span: Optional[int] = Field(default=12, description="12-column grid span (3, 4, 6, 8, 12)")
    row_span: Optional[int] = Field(default=1, description="2D Freeform row height span (1 to 4 rows)")
    align: Optional[str] = Field(default="left", description="Alignment: left, center, right")
    validation: Optional[FieldValidationModel] = Field(default_factory=FieldValidationModel)

class SectionModel(BaseModel):
    id: str = Field(description="Unique section identifier, e.g., sec_1")
    title: str = Field(description="Section header title")
    fields: List[FieldModel] = Field(default_factory=list, description="Array of form fields inside this section")

class FormSchemaResponse(BaseModel):
    title: str = Field(description="Form main title")
    description: Optional[str] = Field(default="", description="Form short summary description")
    sections: List[SectionModel] = Field(default_factory=list, description="Array of form sections")

