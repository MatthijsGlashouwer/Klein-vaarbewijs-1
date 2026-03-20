import AppKit
import Foundation
import PDFKit

let arguments = CommandLine.arguments

guard arguments.count == 3 else {
    fputs("Usage: pdfkit_extract.swift <pdf-path> <render-dir>\n", stderr)
    exit(1)
}

let pdfPath = arguments[1]
let renderDirectory = arguments[2]

try? FileManager.default.createDirectory(
    atPath: renderDirectory,
    withIntermediateDirectories: true
)

guard let document = PDFDocument(url: URL(fileURLWithPath: pdfPath)) else {
    fputs("Unable to open PDF.\n", stderr)
    exit(1)
}

var pages: [[String: Any]] = []

for index in 0..<document.pageCount {
    guard let page = document.page(at: index) else {
        continue
    }

    let bounds = page.bounds(for: .mediaBox)
    let scale: CGFloat = 2.0
    let width = Int(bounds.width * scale)
    let height = Int(bounds.height * scale)

    guard let representation = NSBitmapImageRep(
        bitmapDataPlanes: nil,
        pixelsWide: width,
        pixelsHigh: height,
        bitsPerSample: 8,
        samplesPerPixel: 4,
        hasAlpha: true,
        isPlanar: false,
        colorSpaceName: .deviceRGB,
        bytesPerRow: 0,
        bitsPerPixel: 0
    ) else {
        continue
    }

    NSGraphicsContext.saveGraphicsState()

    if let context = NSGraphicsContext(bitmapImageRep: representation) {
        NSGraphicsContext.current = context
        context.cgContext.setFillColor(NSColor.white.cgColor)
        context.cgContext.fill(CGRect(x: 0, y: 0, width: width, height: height))
        context.cgContext.scaleBy(x: scale, y: scale)
        page.draw(with: .mediaBox, to: context.cgContext)
    }

    NSGraphicsContext.restoreGraphicsState()

    let outputUrl = URL(fileURLWithPath: renderDirectory)
        .appendingPathComponent(String(format: "page-%02d.png", index + 1))

    try representation
        .representation(using: .png, properties: [:])?
        .write(to: outputUrl)

    pages.append([
        "page": index + 1,
        "text": page.string ?? "",
    ])
}

let payload: [String: Any] = [
    "page_count": document.pageCount,
    "pages": pages,
]

let json = try JSONSerialization.data(withJSONObject: payload, options: [.prettyPrinted])
FileHandle.standardOutput.write(json)
