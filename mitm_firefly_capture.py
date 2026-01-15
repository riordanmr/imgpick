# save as firefly_capture.py
# from Microsoft Copilot  2026-01-12  as run by MRR.
import os
import json
from mitmproxy import http

OUTPUT_DIR_JSON = "firefly_json"
OUTPUT_DIR_PNG = "firefly_png"
OUTPUT_DIR_HEADERS = "firefly_headers"

def load(l):
    if not os.path.exists(OUTPUT_DIR_JSON):
        os.makedirs(OUTPUT_DIR_JSON)
    if not os.path.exists(OUTPUT_DIR_PNG):
        os.makedirs(OUTPUT_DIR_PNG)
    if not os.path.exists(OUTPUT_DIR_HEADERS):
        os.makedirs(OUTPUT_DIR_HEADERS)

def extract_json_from_graphql(data: str) -> str:
    """
    Extract JSON data from a GraphQL multipart response string.
    
    Args:
        data: String containing GraphQL response with multipart boundaries
        
    Returns:
        Extracted JSON string from the GraphQL response
        
    Raises:
        ValueError: If no valid JSON found
    """
    # The format can be:
    # 1. Just JSON followed by --graphql-- marker
    # 2. --graphql boundary with headers, then JSON, then --graphql-- marker
    # 3. No graphql.

    if '--graphql' not in data:
        # No graphql boundaries, assume whole data is JSON
        return data.strip()
    
    # Split on --graphql to get parts
    parts = data.split('--graphql')
    
    # Find the part containing JSON
    json_str = None
    
    for part in parts:
        part = part.strip().strip('-')  # Remove dashes and whitespace
        
        # Skip empty parts
        if not part:
            continue
            
        # Skip parts that are just headers (like "content-type: application/json")
        if ':' in part and '\n' not in part.split(':', 1)[1][:50]:
            continue
        
        # If there are headers followed by JSON, split on double newline
        if '\n\n' in part or '\r\n\r\n' in part:
            # Split on double newline to separate headers from body
            if '\r\n\r\n' in part:
                potential_json = part.split('\r\n\r\n', 1)[-1]
            else:
                potential_json = part.split('\n\n', 1)[-1]
            
            potential_json = potential_json.strip()
            if potential_json and potential_json.startswith('{'):
                json_str = potential_json
                break
        elif part.startswith('{'):
            # This part looks like JSON
            json_str = part
            break
    
    if not json_str:
        raise ValueError("No JSON content found in GraphQL response")
    
    return json_str


def response(flow: http.HTTPFlow):
    print ("Response! URL: " + flow.request.url + " content-type: " + flow.response.headers.get("content-type", ""))
   
    # Check if response body length (download) is greater than 1800000 and save request headers
    response_body_length = len(flow.response.content) if flow.response.content else 0
    print("ZZZ response body length: " + str(response_body_length))
    try:
        if response_body_length > 1800000:
            print ("ZZZ Got big response content: " + str(response_body_length))
            path = flow.request.path.replace("/", "_").replace("?", "_")
            headers_filename = f"{flow.request.timestamp_start}_{path}_headers.txt"
            headers_filepath = os.path.join(OUTPUT_DIR_HEADERS, headers_filename)
            
            try:
                with open(headers_filepath, "w", encoding="utf-8") as f:
                    f.write(f"URL: {flow.request.url}\n")
                    f.write(f"Method: {flow.request.method}\n")
                    f.write(f"Response Body Length: {response_body_length}\n")
                    f.write("\nRequest Headers:\n")
                    f.write("-" * 50 + "\n")
                    for name, value in flow.request.headers.items():
                        f.write(f"{name}: {value}\n")
                    f.write("\nResponse Headers:\n")
                    f.write("-" * 50 + "\n")
                    for name, value in flow.response.headers.items():
                        f.write(f"{name}: {value}\n")
                print(f"Saved large response headers to {headers_filepath}")
            except Exception as e:
                print(f"Error writing headers file {headers_filepath}: {e}")
    except Exception as e:
        print(f"Error checking response body length: {e}")
    
    # int ("Response! URL: " + flow.request.url + " content-type: " + flow.response.headers.get("content-type", ""))
   
    # Adjust this to match the Firefly API domain you see in your traffic
    # print ("pretty_host: " + flow.request.pretty_host)
    #if "adobe.io" not in flow.request.pretty_host:
    #    return
    if "adobe.io" not in flow.request.pretty_host:
        print ("Ignoring " + flow.request.pretty_host)
        return

    # Only capture JSON responses
    # if "application/json" not in flow.response.headers.get("content-type", ""):
    #     return

    if "image/png" in flow.response.headers.get("content-type", ""):
        # Save PNG image
        path = flow.request.path.replace("/", "_").replace("?", "_")
        filename = f"{flow.request.timestamp_start}_{path}.png"
        filepath = os.path.join(OUTPUT_DIR_PNG, filename)
        try:
            with open(filepath, "wb") as f:
                f.write(flow.response.content)
        except Exception as e:
            print(f"Error! writing file {filepath}: {e}")
        return  
    elif "graphql" in flow.response.headers.get("content-type", ""):
        # Build a safe filename
        path = flow.request.path.replace("/", "_").replace("?", "_")
        filename = f"{flow.request.timestamp_start}_{path}.json"
        filepath = os.path.join(OUTPUT_DIR_JSON, filename)

        try:
            data = flow.response
            json_str = extract_json_from_graphql(data.text)
            with open(filepath, "w", encoding="utf-8") as f:
                json.dump(json.loads(json_str), f, indent=2)
                #f.write(json_str)
        except Exception as e:
            print(f"Error! writing file {filepath}: {e}")
            return
    
        # # Write the JSON body to disk
        # try:
        #     data = flow.response.json()
        # except Exception as e:
        #     print(f"Error! {e}")
        #     return  # skip non‑JSON or malformed responses

        # with open(filepath, "w", encoding="utf-8") as f:
        #     json.dump(data, f, indent=2)
